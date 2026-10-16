@extends('layouts.app')

@section('page-eyebrow', 'Secure Checkout')
@section('page-title', 'Complete License Payment')

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body d-flex flex-column align-items-center text-center py-5">
            <div class="tracker-stat-icon bg-primary-subtle text-primary mb-3"><i class="fa-solid fa-lock"></i></div>
            <h2 class="tracker-card-title">{{ $license->plan->name }}</h2>
            <p class="text-muted">{{ $transaction->type === 'renewal' ? 'License renewal' : 'New license' }} · ₹{{ number_format($transaction->amount, 2) }}</p>

            @if ($checkout['provider'] === 'razorpay')
                <button id="checkout-start" type="button" class="btn tracker-primary-btn">Pay securely</button>
                <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                <script>
                    document.getElementById('checkout-start').addEventListener('click', function () {
                        const checkout = new Razorpay({
                            key: @json($checkout['key_id']),
                            order_id: @json($checkout['order_id']),
                            amount: @json($checkout['amount']),
                            currency: @json($checkout['currency']),
                            name: @json(config('app.name')),
                            description: @json($transaction->type === 'renewal' ? 'License renewal' : 'License purchase'),
                            handler: async function (response) {
                                const result = await fetch(@json($checkout['verify_url']), {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify(response)
                                });
                                window.location.assign(@json($checkout['return_url']) + (result.ok ? '?payment=confirmed' : '?payment=pending'));
                            }
                        });
                        checkout.open();
                    });
                </script>
            @elseif ($checkout['provider'] === 'cashfree')
                <button id="checkout-start" type="button" class="btn tracker-primary-btn">Pay securely</button>
                <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
                <script>
                    document.getElementById('checkout-start').addEventListener('click', async function () {
                        const cashfree = Cashfree({ mode: @json($checkout['environment'] === 'test' ? 'sandbox' : 'production') });
                        await cashfree.checkout({ paymentSessionId: @json($checkout['payment_session_id']), redirectTarget: '_self' });
                    });
                </script>
            @elseif ($checkout['provider'] === 'payu')
                <form id="payu-checkout" method="POST" action="{{ $checkout['endpoint'] }}">
                    @foreach ($checkout['fields'] as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <button type="submit" class="btn tracker-primary-btn">Continue to PayU</button>
                </form>
                <script>document.getElementById('payu-checkout').requestSubmit();</script>
            @endif
        </div>
    </div>
@endsection