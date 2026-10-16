<?php

namespace App\Http\Requests\Api\V1;

use App\Models\FailedLogin;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TokenLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_id' => ['required', 'string', 'max:191'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials without touching
     * the stateful web session — this issues a bearer token instead.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $user = User::query()->where('email', $this->string('email')->toString())->first();

        if (! $user || ! Hash::check($this->string('password')->toString(), $user->password)) {
            RateLimiter::hit($this->throttleKey());

            FailedLogin::create([
                'user_id' => $user?->id,
                'email' => $this->string('email')->toString(),
                'ip_address' => $this->ip(),
                'user_agent' => $this->userAgent(),
                'reason' => 'Invalid credentials',
                'attempted_at' => now(),
            ]);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
