<?php

namespace App\Http\Controllers\User;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\LicensePlan;
use App\Models\LicenseTransaction;
use App\Models\User;
use App\Models\UserLicense;
use App\Services\LicensePaymentService;
use App\Services\PaymentGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LicenseController extends Controller
{
    public function __construct(
        protected LicensePaymentService $payments,
        protected PaymentGatewayService $gateway,
    ) {}

    public function index(): View
    {
        $user = User::query()->findOrFail(Auth::id());

        return view('user.licenses.index', [
            'licenses' => $user->userLicenses()->with(['plan', 'assignedTrackedUser'])->latest()->paginate(10),
            'activeLicense' => $user->userLicenses()->with('plan')->usable()->latest('expiry_date')->first(),
            'availableLicenses' => $user->userLicenses()->where('status', 'pending')->where('payment_status', 'paid')->count(),
            'trackedUsers' => $user->trackedUsers()->where('status', 'active')->count(),
            'transactions' => $user->licenseTransactions()->with(['plan', 'license'])->latest()->take(10)->get(),
        ]);
    }

    public function plans(): View
    {
        $user = User::query()->findOrFail(Auth::id());
        $freeLicenseClaimed = app(\App\Services\LicenseService::class)->hasFreeLicenseHistory($user);

        return view('user.licenses.plans', [
            'plans' => LicensePlan::query()->where('status', 'active')->orderBy('display_order')->get(),
            'freeLicenseClaimed' => $freeLicenseClaimed,
        ]);
    }

    public function purchase(Request $request): View|RedirectResponse
    {
        $data = $request->validate(['license_plan_id' => ['required', 'integer', 'exists:license_plans,id']]);
        $plan = LicensePlan::query()->where('status', 'active')->findOrFail($data['license_plan_id']);
        $result = $this->payments->beginPurchase($request->user(), $plan);

        if (! isset($result['transaction'])) {
            return redirect()->route('my-tracking.create')->with('status', 'Free license added. Create a relation to start its validity.');
        }

        return $this->checkoutResponse($result);
    }

    public function renew(Request $request, UserLicense $userLicense): View|RedirectResponse
    {
        $result = $this->payments->beginRenewal($request->user(), $userLicense);

        return $this->checkoutResponse($result);
    }

    public function paymentReturn(Request $request, LicenseTransaction $transaction): RedirectResponse
    {
        abort_unless((int) $transaction->user_id === (int) $request->user()->id, 403);

        return $this->verifyAndRedirect($transaction, []);
    }

    public function verifyRazorpay(Request $request, LicenseTransaction $transaction): RedirectResponse
    {
        abort_unless((int) $transaction->user_id === (int) $request->user()->id, 403);
        $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        return $this->verifyAndRedirect($transaction, $request->only([
            'razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature',
        ]));
    }

    public function payuCallback(Request $request): RedirectResponse
    {
        $transaction = $this->payments->findPendingPayUTransaction((string) $request->input('txnid'));
        abort_unless($transaction, 404);

        return $this->verifyAndRedirect($transaction, $request->all());
    }

    protected function verifyAndRedirect(LicenseTransaction $transaction, array $payload): RedirectResponse
    {
        if ($transaction->status === PaymentStatus::Paid) {
            return redirect()->route('my-licenses.index')->with('status', 'Payment is already confirmed.');
        }

        try {
            $paymentId = $this->gateway->verify($transaction, $payload);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('my-licenses.index')->with('error', 'Payment verification is temporarily unavailable. The license remains unavailable until confirmation.');
        }
        if ($paymentId) {
            $this->payments->complete($transaction, $paymentId);

            return redirect()->route('my-licenses.index')->with('status', 'Payment confirmed. Your license is ready to use.');
        }

        return redirect()->route('my-licenses.index')->with('error', 'Payment is not confirmed yet. The license remains unavailable until confirmation.');
    }

    protected function checkoutResponse(array $result): View|RedirectResponse
    {
        $checkout = $result['checkout'];

        if (isset($checkout['redirect_url'])) {
            return redirect()->away($checkout['redirect_url']);
        }

        return view('user.licenses.checkout', [
            'license' => $result['license'],
            'transaction' => $result['transaction'],
            'checkout' => $checkout,
        ]);
    }
}
