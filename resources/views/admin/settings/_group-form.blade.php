@php
    $settings = $groups->get($groupKey, collect())->reject(fn ($setting) => $setting->type === 'file');

    // Har field type ke liye ek Font Awesome icon — form ko visually scannable banane ke liye
    $iconMap = [
        'secret' => 'fa-solid fa-key',
        'boolean' => 'fa-solid fa-toggle-on',
        'integer' => 'fa-solid fa-hashtag',
        'json' => 'fa-solid fa-code',
        'text' => 'fa-solid fa-pen',
    ];

    $gatewayMeta = [
        'razorpay' => ['label' => 'Razorpay', 'color' => '#0C2451'],
        'phonepe' => ['label' => 'PhonePe', 'color' => '#5F259F'],
        'cashfree' => ['label' => 'Cashfree', 'color' => '#00B899'],
        'payu' => ['label' => 'PayU', 'color' => '#5D24AC'],
    ];
@endphp

@push('styles')
<style>
    /* Field-level styling — settings-scope ke gradient/glass theme ko extend karta hai */
    .settings-scope .st-field { margin-bottom: 1.5rem; }
    .settings-scope [data-payment-credential][hidden] { display: none !important; }

    .settings-scope .st-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--st-text);
        margin-bottom: 0.5rem;
        letter-spacing: 0.01em;
    }

    .settings-scope .st-label i {
        width: 26px;
        height: 26px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: rgba(124, 58, 237, 0.1);
        color: var(--st-primary);
        font-size: 0.75rem;
    }

    .settings-scope .st-input,
    .settings-scope .st-select,
    .settings-scope textarea.st-input {
        width: 100%;
        background: #fff;
        border: 1.5px solid #E5E1F2;
        border-radius: 10px;
        padding: 0.65rem 0.9rem;
        font-size: 0.9rem;
        color: var(--st-text);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .settings-scope .st-input:focus,
    .settings-scope .st-select:focus,
    .settings-scope textarea.st-input:focus {
        outline: none;
        border-color: var(--st-primary);
        box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.12);
    }

    .settings-scope .st-input::placeholder { color: #A6A1B8; }

    .settings-scope .st-hint {
        display: block;
        font-size: 0.76rem;
        color: var(--st-text-muted);
        margin-top: 0.35rem;
    }

    /* Custom gradient toggle switch — boolean settings ke liye */
    .settings-scope .st-switch {
        position: relative;
        display: inline-block;
        width: 46px;
        height: 26px;
    }
    .settings-scope .st-switch input { opacity: 0; width: 0; height: 0; }
    .settings-scope .st-switch .st-slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background: #E5E1F2;
        border-radius: 34px;
        transition: 0.2s;
    }
    .settings-scope .st-switch .st-slider::before {
        content: "";
        position: absolute;
        height: 20px; width: 20px;
        left: 3px; bottom: 3px;
        background: #fff;
        border-radius: 50%;
        transition: 0.2s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .settings-scope .st-switch input:checked + .st-slider { background: var(--st-gradient); }
    .settings-scope .st-switch input:checked + .st-slider::before { transform: translateX(20px); }

    /* Payment gateway — dropdown ki jagah visual selector cards */
    .settings-scope .st-gateway-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 0.75rem;
    }
    .settings-scope .st-gateway-option { position: relative; }
    .settings-scope .st-gateway-option input { position: absolute; opacity: 0; }
    .settings-scope .st-gateway-option label {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        padding: 1rem 0.5rem;
        border: 1.5px solid #E5E1F2;
        border-radius: 12px;
        cursor: pointer;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--st-text-muted);
        transition: all 0.15s ease;
        text-align: center;
    }
    .settings-scope .st-gateway-option .st-dot {
        width: 34px; height: 34px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 0.9rem;
    }
    .settings-scope .st-gateway-option input:checked + label {
        border-color: var(--st-primary);
        background: rgba(124, 58, 237, 0.06);
        color: var(--st-primary);
        box-shadow: 0 4px 12px rgba(124, 58, 237, 0.15);
    }

    /* Environment — segmented pill control (Test / Live) */
    .settings-scope .st-segment {
        display: inline-flex;
        background: #F3F1FA;
        border-radius: 10px;
        padding: 4px;
        gap: 4px;
    }
    .settings-scope .st-segment input { position: absolute; opacity: 0; }
    .settings-scope .st-segment label {
        padding: 0.45rem 1.25rem;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--st-text-muted);
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .settings-scope .st-segment input:checked + label {
        background: #fff;
        color: var(--st-primary);
        box-shadow: 0 2px 6px rgba(30,27,46,0.1);
    }

    /* File upload — dashed drop zone look */
    .settings-scope .st-upload {
        border: 1.5px dashed #C9C2E8;
        border-radius: 12px;
        padding: 1.1rem;
        text-align: center;
        background: rgba(124, 58, 237, 0.03);
        transition: border-color 0.15s ease;
    }
    .settings-scope .st-upload:hover { border-color: var(--st-primary); }
    .settings-scope .st-upload i {
        font-size: 1.3rem;
        color: var(--st-primary);
        margin-bottom: 0.4rem;
        display: block;
    }
    .settings-scope .st-upload input[type="file"] {
        font-size: 0.8rem;
        margin-top: 0.5rem;
    }

    .settings-scope .st-submit-row {
        display: flex;
        justify-content: flex-end;
        border-top: 1px solid var(--st-border);
        padding-top: 1.5rem;
        margin-top: 0.5rem;
    }

    .settings-scope .st-submit-btn {
        background: var(--st-gradient);
        border: none;
        color: #fff;
        font-weight: 600;
        font-size: 0.88rem;
        padding: 0.7rem 1.75rem;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 6px 16px rgba(124, 58, 237, 0.3);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .settings-scope .st-submit-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(124, 58, 237, 0.38);
        color: #fff;
    }

    .settings-scope .st-empty {
        text-align: center;
        padding: 2.5rem 1rem;
        color: var(--st-text-muted);
        font-size: 0.88rem;
    }
    .settings-scope .st-empty i {
        font-size: 1.6rem;
        color: #D8D2EE;
        display: block;
        margin-bottom: 0.6rem;
    }
</style>
@endpush

<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" @if ($groupKey === 'payments') data-payment-settings-form @endif>
    @csrf
    @method('PUT')
    <input type="hidden" name="group" value="{{ $groupKey }}">

    <div class="row g-4">
        @forelse ($settings as $setting)
            <div class="col-md-6" @if ($groupKey === 'payments' && $setting->type === 'secret') data-payment-credential="{{ $setting->key }}" @endif>
                <div class="st-field">

                    @if ($groupKey === 'payments' && $setting->key === 'gateway')
                        {{-- Gateway ko dropdown ki jagah selectable card grid ke roop mein dikhayein --}}
                        <label class="st-label"><i class="fa-solid fa-credit-card"></i>Payment Gateway</label>
                        <div class="st-gateway-grid">
                            @foreach ($gatewayMeta as $value => $meta)
                                <div class="st-gateway-option">
                                    <input type="radio" name="values[{{ $setting->key }}]" id="gateway_{{ $value }}" value="{{ $value }}" @checked($setting->value === $value)>
                                    <label for="gateway_{{ $value }}">
                                        <span class="st-dot" style="background: {{ $meta['color'] }}">
                                            <i class="fa-solid fa-building-columns"></i>
                                        </span>
                                        {{ $meta['label'] }}
                                    </label>
                                </div>
                            @endforeach
                        </div>

                    @elseif ($groupKey === 'payments' && $setting->key === 'environment')
                        {{-- Test/Live ke liye segmented pill switch --}}
                        <label class="st-label"><i class="fa-solid fa-flask"></i>Environment</label>
                        <div class="st-segment">
                            <input type="radio" name="values[{{ $setting->key }}]" id="env_test" value="test" @checked($setting->value === 'test')>
                            <label for="env_test">Test</label>
                            <input type="radio" name="values[{{ $setting->key }}]" id="env_live" value="live" @checked($setting->value === 'live')>
                            <label for="env_live">Live</label>
                        </div>

                    @elseif ($setting->type === 'secret')
                        <label class="st-label" for="setting_{{ $setting->key }}"><i class="{{ $iconMap['secret'] }}"></i>{{ \Illuminate\Support\Str::of($setting->key)->replace('_', ' ')->headline() }}</label>
                        <input type="password" name="values[{{ $setting->key }}]" id="setting_{{ $setting->key }}" class="st-input" value="" autocomplete="new-password" placeholder="{{ $setting->value ? 'Securely saved hai — blank chhodein current value rakhne ke liye' : 'Credential daalein' }}">

                    @elseif ($setting->type === 'boolean')
                        <label class="st-label" for="setting_{{ $setting->key }}"><i class="{{ $iconMap['boolean'] }}"></i>{{ \Illuminate\Support\Str::of($setting->key)->replace('_', ' ')->headline() }}</label>
                        <label class="st-switch">
                            <input type="checkbox" name="values[{{ $setting->key }}]" id="setting_{{ $setting->key }}" value="1" @checked($setting->value)>
                            <span class="st-slider"></span>
                        </label>

                    @elseif ($setting->type === 'integer')
                        <label class="st-label" for="setting_{{ $setting->key }}"><i class="{{ $iconMap['integer'] }}"></i>{{ \Illuminate\Support\Str::of($setting->key)->replace('_', ' ')->headline() }}</label>
                        <input type="number" name="values[{{ $setting->key }}]" id="setting_{{ $setting->key }}" class="st-input" value="{{ $setting->value }}" @if ($setting->key === 'location_save_radius_meters') min="1" max="65535" step="1" required @endif>

                    @elseif ($setting->type === 'json')
                        <label class="st-label" for="setting_{{ $setting->key }}"><i class="{{ $iconMap['json'] }}"></i>{{ \Illuminate\Support\Str::of($setting->key)->replace('_', ' ')->headline() }}</label>
                        <textarea name="values[{{ $setting->key }}]" id="setting_{{ $setting->key }}" rows="3" class="st-input">{{ json_encode($setting->value) }}</textarea>

                    @else
                        <label class="st-label" for="setting_{{ $setting->key }}"><i class="{{ $iconMap['text'] }}"></i>{{ \Illuminate\Support\Str::of($setting->key)->replace('_', ' ')->headline() }}</label>
                        <input type="text" name="values[{{ $setting->key }}]" id="setting_{{ $setting->key }}" class="st-input" value="{{ $setting->value }}">
                    @endif

                    @if ($setting->description)
                        <span class="st-hint">{{ $setting->description }}</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="st-empty">
                    <i class="fa-solid fa-folder-open"></i>
                    Is group mein abhi koi settings configure nahi hain.
                </div>
            </div>
        @endforelse

        @if ($groupKey === 'site')
            <div class="col-md-6">
                <div class="st-field">
                    <label class="st-label" for="logo"><i class="fa-solid fa-image"></i>Site Logo</label>
                    @php $logo = $groups->get('site', collect())->firstWhere('key', 'logo'); @endphp
                    <div class="st-upload">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        @if ($logo?->value)
                            <img src="{{ asset('storage/'.$logo->value) }}" alt="Current site logo" style="max-width: 220px; max-height: 72px; object-fit: contain;">
                        @else
                            <span class="st-hint mb-0">PNG ya SVG upload karein</span>
                        @endif
                        <input type="file" name="logo" id="logo" accept="image/*" class="form-control form-control-sm">
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="st-field">
                    <label class="st-label" for="favicon"><i class="fa-solid fa-star"></i>Favicon</label>
                    @php $favicon = $groups->get('site', collect())->firstWhere('key', 'favicon'); @endphp
                    <div class="st-upload">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        @if ($favicon?->value)
                            <img src="{{ asset('storage/'.$favicon->value) }}" alt="Current favicon" style="width: 32px; height: 32px; object-fit: contain;">
                        @else
                            <span class="st-hint mb-0">32x32 ICO/PNG upload karein</span>
                        @endif
                        <input type="file" name="favicon" id="favicon" accept="image/*" class="form-control form-control-sm">
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="st-submit-row">
        <button type="submit" class="st-submit-btn">
            <i class="fa-solid fa-floppy-disk"></i>
            {{ ucfirst($groupKey) }} Settings Save Karein
        </button>
    </div>
</form>

@if ($groupKey === 'payments')
    @push('scripts')
        <script>
            (() => {
                const form = document.querySelector('[data-payment-settings-form]');
                if (!form) return;

                const updateCredentialFields = () => {
                    const gateway = form.querySelector('[name="values[gateway]"]:checked')?.value;
                    const environment = form.querySelector('[name="values[environment]"]:checked')?.value;

                    form.querySelectorAll('[data-payment-credential]').forEach((field) => {
                        const visible = field.dataset.paymentCredential.startsWith(`${gateway}_${environment}_`);
                        field.hidden = !visible;
                        field.querySelectorAll('input').forEach((input) => {
                            input.disabled = !visible;
                        });
                    });
                };

                form.querySelectorAll('[name="values[gateway]"], [name="values[environment]"]').forEach((input) => {
                    input.addEventListener('change', updateCredentialFields);
                });

                updateCredentialFields();
            })();
        </script>
    @endpush
@endif
