<?php

namespace App\Services;

use App\Models\LicenseTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentGatewayService
{
    public function __construct(protected SettingsService $settings) {}

    public function gateway(): string
    {
        return (string) $this->settings->get('payments', 'gateway', 'razorpay');
    }

    public function environment(): string
    {
        return (string) $this->settings->get('payments', 'environment', 'test');
    }

    public function start(LicenseTransaction $transaction): array
    {
        return match ($transaction->gateway) {
            'razorpay' => $this->startRazorpay($transaction),
            'phonepe' => $this->startPhonePe($transaction),
            'cashfree' => $this->startCashfree($transaction),
            'payu' => $this->startPayU($transaction),
            default => throw ValidationException::withMessages(['payment' => 'Select a supported payment gateway in settings.']),
        };
    }

    public function verify(LicenseTransaction $transaction, array $payload): ?string
    {
        return match ($transaction->gateway) {
            'razorpay' => $this->verifyRazorpay($transaction, $payload),
            'phonepe' => $this->verifyPhonePe($transaction),
            'cashfree' => $this->verifyCashfree($transaction),
            'payu' => $this->verifyPayU($transaction, $payload),
            default => null,
        };
    }

    protected function startRazorpay(LicenseTransaction $transaction): array
    {
        $keyId = $this->credential('razorpay', $transaction->environment, 'key_id');
        $keySecret = $this->credential('razorpay', $transaction->environment, 'key_secret');
        $amount = (int) round((float) $transaction->amount * 100);
        $response = Http::withBasicAuth($keyId, $keySecret)
            ->acceptJson()
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $amount,
                'currency' => 'INR',
                'receipt' => 'ltx_'.$transaction->id,
            ])->throw()->json();

        $transaction->update(['provider_order_id' => $response['id']]);

        return [
            'provider' => 'razorpay',
            'key_id' => $keyId,
            'order_id' => $response['id'],
            'amount' => $amount,
            'currency' => 'INR',
            'verify_url' => route('my-licenses.payment-verify', $transaction->uuid),
            'return_url' => route('my-licenses.index'),
        ];
    }

    protected function startPhonePe(LicenseTransaction $transaction): array
    {
        $base = $transaction->environment === 'test'
            ? 'https://api-preprod.phonepe.com/apis/pg-sandbox'
            : 'https://api.phonepe.com/apis/pg';
        $tokenBase = $transaction->environment === 'test'
            ? $base
            : 'https://api.phonepe.com/apis/identity-manager';
        $token = Http::asForm()->post($tokenBase.'/v1/oauth/token', [
            'client_id' => $this->credential('phonepe', $transaction->environment, 'client_id'),
            'client_version' => $this->credential('phonepe', $transaction->environment, 'client_version'),
            'client_secret' => $this->credential('phonepe', $transaction->environment, 'client_secret'),
            'grant_type' => 'client_credentials',
        ])->throw()->json('access_token');

        $merchantOrderId = 'LT_'.$transaction->id.'_'.Str::lower(Str::random(8));
        $response = Http::withToken($token, 'O-Bearer')->post($base.'/checkout/v2/pay', [
            'merchantOrderId' => $merchantOrderId,
            'amount' => (int) round((float) $transaction->amount * 100),
            'expireAfter' => 1200,
            'paymentFlow' => [
                'type' => 'PG_CHECKOUT',
                'merchantUrls' => ['redirectUrl' => route('my-licenses.payment-return', ['transaction' => $transaction->uuid])],
            ],
        ])->throw()->json();

        $transaction->update([
            'provider_order_id' => $merchantOrderId,
            'metadata' => ['phonepe_order_id' => $response['orderId'] ?? null],
        ]);

        return ['provider' => 'phonepe', 'redirect_url' => $response['redirectUrl']];
    }

    protected function startCashfree(LicenseTransaction $transaction): array
    {
        $user = $transaction->user;
        $phone = preg_replace('/\D+/', '', (string) $user->phone);
        if (strlen($phone) < 10) {
            throw ValidationException::withMessages(['payment' => 'Add a valid phone number to your profile before checkout.']);
        }

        $base = $transaction->environment === 'test' ? 'https://sandbox.cashfree.com/pg' : 'https://api.cashfree.com/pg';
        $orderId = 'lt_'.$transaction->id.'_'.Str::lower(Str::random(5));
        $response = Http::withHeaders([
            'x-client-id' => $this->credential('cashfree', $transaction->environment, 'app_id'),
            'x-client-secret' => $this->credential('cashfree', $transaction->environment, 'secret_key'),
            'x-api-version' => '2023-08-01',
        ])->post($base.'/orders', [
            'order_id' => $orderId,
            'order_amount' => (float) $transaction->amount,
            'order_currency' => 'INR',
            'customer_details' => [
                'customer_id' => 'u'.$user->id,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_phone' => substr($phone, -10),
            ],
            'order_meta' => ['return_url' => route('my-licenses.payment-return', ['transaction' => $transaction->uuid])],
        ])->throw()->json();

        $transaction->update(['provider_order_id' => $orderId]);

        return [
            'provider' => 'cashfree',
            'environment' => $transaction->environment,
            'payment_session_id' => $response['payment_session_id'],
            'return_url' => route('my-licenses.index'),
        ];
    }

    protected function startPayU(LicenseTransaction $transaction): array
    {
        $key = $this->credential('payu', $transaction->environment, 'merchant_key');
        $salt = $this->credential('payu', $transaction->environment, 'salt');
        $user = $transaction->user;
        $txnId = Str::upper(Str::random(20));
        $productInfo = $transaction->type === 'renewal' ? 'License renewal' : 'License purchase';
        $amount = number_format((float) $transaction->amount, 2, '.', '');
        $fields = [
            'key' => $key,
            'txnid' => $txnId,
            'amount' => $amount,
            'productinfo' => $productInfo,
            'firstname' => $user->name,
            'email' => $user->email,
            'phone' => preg_replace('/\D+/', '', (string) $user->phone),
            'surl' => route('payments.payu.callback'),
            'furl' => route('payments.payu.callback'),
        ];
        $fields['hash'] = hash('sha512', implode('|', [
            $fields['key'], $fields['txnid'], $fields['amount'], $fields['productinfo'], $fields['firstname'], $fields['email'],
            '', '', '', '', '', '', '', '', '', '', $salt,
        ]));

        $transaction->update(['provider_order_id' => $txnId]);

        return [
            'provider' => 'payu',
            'endpoint' => $transaction->environment === 'test' ? 'https://test.payu.in/_payment' : 'https://secure.payu.in/_payment',
            'fields' => $fields,
        ];
    }

    protected function verifyRazorpay(LicenseTransaction $transaction, array $payload): ?string
    {
        if (($payload['razorpay_order_id'] ?? null) !== $transaction->provider_order_id) {
            return null;
        }

        $expected = hash_hmac(
            'sha256',
            $transaction->provider_order_id.'|'.($payload['razorpay_payment_id'] ?? ''),
            $this->credential('razorpay', $transaction->environment, 'key_secret'),
        );

        if (! hash_equals($expected, (string) ($payload['razorpay_signature'] ?? ''))) {
            return null;
        }

        $paymentId = (string) $payload['razorpay_payment_id'];
        $payment = Http::withBasicAuth(
            $this->credential('razorpay', $transaction->environment, 'key_id'),
            $this->credential('razorpay', $transaction->environment, 'key_secret'),
        )->get('https://api.razorpay.com/v1/payments/'.$paymentId)->throw()->json();

        if (($payment['status'] ?? null) !== 'captured'
            || ($payment['order_id'] ?? null) !== $transaction->provider_order_id
            || (int) ($payment['amount'] ?? 0) !== (int) round((float) $transaction->amount * 100)) {
            return null;
        }

        return $paymentId;
    }

    protected function verifyPhonePe(LicenseTransaction $transaction): ?string
    {
        $base = $transaction->environment === 'test'
            ? 'https://api-preprod.phonepe.com/apis/pg-sandbox'
            : 'https://api.phonepe.com/apis/pg';
        $tokenBase = $transaction->environment === 'test'
            ? $base
            : 'https://api.phonepe.com/apis/identity-manager';
        $token = Http::asForm()->post($tokenBase.'/v1/oauth/token', [
            'client_id' => $this->credential('phonepe', $transaction->environment, 'client_id'),
            'client_version' => $this->credential('phonepe', $transaction->environment, 'client_version'),
            'client_secret' => $this->credential('phonepe', $transaction->environment, 'client_secret'),
            'grant_type' => 'client_credentials',
        ])->throw()->json('access_token');
        $response = Http::withToken($token, 'O-Bearer')
            ->get($base.'/checkout/v2/order/'.$transaction->provider_order_id.'/status', ['details' => 'true'])
            ->throw()->json();

        if (($response['state'] ?? null) !== 'COMPLETED' || (int) ($response['amount'] ?? 0) !== (int) round((float) $transaction->amount * 100)) {
            return null;
        }

        return data_get($response, 'paymentDetails.0.transactionId');
    }

    protected function verifyCashfree(LicenseTransaction $transaction): ?string
    {
        $base = $transaction->environment === 'test' ? 'https://sandbox.cashfree.com/pg' : 'https://api.cashfree.com/pg';
        $response = Http::withHeaders([
            'x-client-id' => $this->credential('cashfree', $transaction->environment, 'app_id'),
            'x-client-secret' => $this->credential('cashfree', $transaction->environment, 'secret_key'),
            'x-api-version' => '2023-08-01',
        ])->get($base.'/orders/'.$transaction->provider_order_id)->throw()->json();

        if (($response['order_status'] ?? null) !== 'PAID' || (float) ($response['order_amount'] ?? 0) !== (float) $transaction->amount) {
            return null;
        }

        return (string) ($response['cf_order_id'] ?? $transaction->provider_order_id);
    }

    protected function verifyPayU(LicenseTransaction $transaction, array $payload): ?string
    {
        if (($payload['txnid'] ?? null) !== $transaction->provider_order_id || ($payload['status'] ?? null) !== 'success') {
            return null;
        }

        $key = $this->credential('payu', $transaction->environment, 'merchant_key');
        $salt = $this->credential('payu', $transaction->environment, 'salt');
        $sequence = implode('|', [
            $salt, $payload['status'], '', '', '', '', '', '', '', '', '', $payload['email'] ?? '',
            $payload['firstname'] ?? '', $payload['productinfo'] ?? '', $payload['amount'] ?? '', $payload['txnid'], $key,
        ]);
        $expected = hash('sha512', $sequence);

        if (! hash_equals($expected, (string) ($payload['hash'] ?? '')) || (float) ($payload['amount'] ?? 0) !== (float) $transaction->amount) {
            return null;
        }

        return (string) ($payload['mihpayid'] ?? $payload['txnid']);
    }

    protected function credential(string $provider, string $environment, string $key): string
    {
        $value = (string) $this->settings->get('payments', "{$provider}_{$environment}_{$key}", '');

        if ($value === '') {
            throw ValidationException::withMessages(['payment' => 'The selected payment gateway is missing required credentials in settings.']);
        }

        return $value;
    }
}