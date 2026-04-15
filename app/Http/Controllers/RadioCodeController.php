<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\RadioCode;
use App\Support\RadioCodeResolver;
use App\Support\SerialClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class RadioCodeController extends Controller
{
    public function __construct(
        private readonly RadioCodeResolver $resolver,
        private readonly SerialClassifier $classifier
    )
    {
    }

    public function index(): View
    {
        return view('welcome');
    }

    public function classify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'serial' => 'required|string|min:2|max:160',
        ]);

        $classification = $this->classifier->classify($validated['serial']);

        return response()->json([
            'success' => true,
            'data' => $classification,
        ]);
    }

    public function search(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'serial' => 'required|string|min:6|max:64',
        ]);

        $inputSerial = $this->resolver->normalizeSerial($validated['serial']);
        $results = $this->resolver->findCandidates($inputSerial);

        if ($results->isEmpty()) {
            return back()->with('error', 'No code found for this serial number.');
        }

        if ($results->count() === 1) {
            $result = $results->first();
            $directRevealEnabled = $this->isDirectRevealEnabled();

            return view('result', [
                'serial' => $inputSerial,
                'brand' => $result->brand,
                'car_make' => $result->car_make,
                'radio_code_id' => $result->id,
                'direct_reveal_enabled' => $directRevealEnabled,
                'direct_code' => $directRevealEnabled ? $result->code : null,
            ]);
        }

        return view('select-model', [
            'serial' => $inputSerial,
            'options' => $results,
        ]);
    }

    public function selectModel(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'serial' => 'required|string|min:6|max:64',
            'radio_code_id' => 'required|integer',
        ]);

        $inputSerial = $this->resolver->normalizeSerial($validated['serial']);
        $result = RadioCode::query()->find($validated['radio_code_id']);
        $directRevealEnabled = $this->isDirectRevealEnabled();

        if (!$result || !$this->resolver->serialMatchesResult($inputSerial, $result)) {
            return back()->with('error', 'Invalid selection.');
        }

        return view('result', [
            'serial' => $inputSerial,
            'brand' => $result->brand,
            'car_make' => $result->car_make,
            'radio_code_id' => $result->id,
            'direct_reveal_enabled' => $directRevealEnabled,
            'direct_code' => $directRevealEnabled ? $result->code : null,
        ]);
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        $validationRules = [
            'serial' => 'required|string|min:6|max:64',
            'radio_code_id' => 'nullable|integer',
        ];

        if (!$this->isDirectRevealEnabled()) {
            $validationRules['email'] = 'required|email|max:100';
        }

        $validated = $request->validate($validationRules);

        $inputSerial = $this->resolver->normalizeSerial($validated['serial']);

        if (isset($validated['radio_code_id'])) {
            $result = RadioCode::query()->find($validated['radio_code_id']);

            if (!$result || !$this->resolver->serialMatchesResult($inputSerial, $result)) {
                return back()->with('error', 'Invalid radio model selection for this serial.');
            }
        } else {
            $results = $this->resolver->findCandidates($inputSerial);

            if ($results->isEmpty()) {
                return back()->with('error', 'Serial not found.');
            }

            if ($results->count() > 1) {
                return view('select-model', [
                    'serial' => $inputSerial,
                    'options' => $results,
                ]);
            }

            $result = $results->first();
        }

        if ($this->isDirectRevealEnabled()) {
            return view('success', [
                'serial' => $inputSerial,
                'code' => $result->code,
                'car_make' => $result->car_make,
                'brand' => $result->brand,
            ]);
        }

        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            return back()->with('error', 'Stripe is not configured on server.');
        }

        Stripe::setApiKey($secret);

        try {
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'Radio Unlock Code - '.$result->car_make,
                            'description' => 'Serial: '.$inputSerial,
                        ],
                        'unit_amount' => 299,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'customer_email' => $validated['email'],
                'success_url' => route('payment.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('home'),
                'metadata' => [
                    'serial_input' => $inputSerial,
                    'serial_lookup' => $result->serial,
                    'email' => $validated['email'],
                    'radio_code_id' => (string) $result->id,
                    'channel' => 'web',
                ],
            ]);
        } catch (ApiErrorException $e) {
            return back()->with('error', 'Unable to start checkout. Please try again.');
        }

        Order::query()->create([
            'serial' => $result->serial,
            'email' => $validated['email'],
            'stripe_session_id' => $session->id,
            'amount' => 2.99,
            'currency' => 'USD',
            'status' => 'pending',
            'brand' => $result->brand,
            'car_make' => $result->car_make,
        ]);

        return redirect($session->url);
    }

    public function success(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string|max:255',
        ]);

        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            return redirect('/')->with('error', 'Stripe is not configured on server.');
        }

        Stripe::setApiKey($secret);

        try {
            $session = Session::retrieve($validated['session_id']);
        } catch (ApiErrorException $e) {
            return redirect('/')->with('error', 'Payment session not found.');
        }

        if ($session->payment_status !== 'paid') {
            return redirect('/')->with('error', 'Payment not completed.');
        }

        $order = Order::query()->where('stripe_session_id', $session->id)->first();

        if (!$order) {
            return redirect('/')->with('error', 'Order not found.');
        }

        if ($order->status !== 'paid' || $order->code_revealed === null) {
            $result = $this->resolveRadioCodeForOrder($order, $session);

            if (!$result) {
                Log::warning('Web code resolution failed', [
                    'order_id' => $order->id,
                    'session_id' => $session->id,
                    'order_serial' => $order->serial,
                    'order_brand' => $order->brand,
                    'order_car_make' => $order->car_make,
                    'metadata_radio_code_id' => isset($session->metadata->radio_code_id) ? (string) $session->metadata->radio_code_id : null,
                    'metadata_serial_lookup' => isset($session->metadata->serial_lookup) ? (string) $session->metadata->serial_lookup : null,
                ]);

                return redirect('/')->with('error', 'Code not found for this order.');
            }

            $order->update([
                'status' => 'paid',
                'code_revealed' => $result->code,
                'stripe_payment_id' => is_string($session->payment_intent) ? $session->payment_intent : null,
            ]);

            $order->refresh();
        }

        if ($order->code_revealed === null) {
            return redirect('/')->with('error', 'Code reveal failed for this order.');
        }

        $displaySerial = isset($session->metadata->serial_input)
            ? (string) $session->metadata->serial_input
            : $order->serial;

        return view('success', [
            'serial' => $displaySerial,
            'code' => $order->code_revealed,
            'car_make' => $order->car_make,
            'brand' => $order->brand,
        ]);
    }

    private function resolveRadioCodeForOrder(Order $order, Session $session): ?RadioCode
    {
        $metadataRadioCodeId = isset($session->metadata->radio_code_id)
            ? (int) $session->metadata->radio_code_id
            : 0;

        if ($metadataRadioCodeId > 0) {
            $byId = RadioCode::query()->find($metadataRadioCodeId);
            if ($byId) {
                return $byId;
            }
        }

        $brand = trim((string) ($order->brand ?? ''));
        $carMake = trim((string) ($order->car_make ?? ''));
        $orderSerial = trim((string) ($order->serial ?? ''));

        if ($orderSerial !== '' && $brand !== '' && $carMake !== '') {
            $exact = RadioCode::query()
                ->where('serial', $orderSerial)
                ->where('brand', $brand)
                ->where('car_make', $carMake)
                ->first();

            if ($exact) {
                return $exact;
            }
        }

        $serialKeys = [];
        if (isset($session->metadata->serial_lookup)) {
            $serialLookup = trim((string) $session->metadata->serial_lookup);
            if ($serialLookup !== '') {
                $serialKeys[] = $serialLookup;
            }
        }

        if ($orderSerial !== '') {
            $serialKeys[] = $orderSerial;
        }

        if (isset($session->metadata->serial_input)) {
            $serialInput = strtoupper(trim((string) $session->metadata->serial_input));
            if ($serialInput !== '') {
                $serialKeys[] = $serialInput;

                $compact = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $serialInput) ?? $serialInput);
                if ($compact !== '') {
                    $serialKeys[] = $compact;
                }
            }
        }

        $serialKeys = array_values(array_unique(array_filter($serialKeys)));
        if ($serialKeys === []) {
            return null;
        }

        $candidates = collect();
        foreach ($serialKeys as $serialKey) {
            $candidates = $candidates->merge(
                RadioCode::query()->where('serial', $serialKey)->get()
            );
        }

        $candidates = $candidates->unique('id')->values();
        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        if ($brand !== '') {
            $brandCandidates = $candidates
                ->filter(static fn (RadioCode $item): bool => strcasecmp($item->brand, $brand) === 0)
                ->values();

            if ($brandCandidates->count() === 1) {
                return $brandCandidates->first();
            }

            if ($carMake !== '') {
                $exactMakeCandidates = $brandCandidates
                    ->filter(static fn (RadioCode $item): bool => strcasecmp($item->car_make, $carMake) === 0)
                    ->values();

                if ($exactMakeCandidates->count() === 1) {
                    return $exactMakeCandidates->first();
                }

                $normalizedOrderMake = $this->normalizeCompareToken($carMake);
                $fuzzyMakeCandidates = $brandCandidates
                    ->filter(function (RadioCode $item) use ($normalizedOrderMake): bool {
                        $normalizedCandidateMake = $this->normalizeCompareToken($item->car_make);

                        if ($normalizedOrderMake === '' || $normalizedCandidateMake === '') {
                            return false;
                        }

                        return $normalizedCandidateMake === $normalizedOrderMake
                            || str_starts_with($normalizedCandidateMake, $normalizedOrderMake)
                            || str_starts_with($normalizedOrderMake, $normalizedCandidateMake);
                    })
                    ->values();

                if ($fuzzyMakeCandidates->count() === 1) {
                    return $fuzzyMakeCandidates->first();
                }
            }
        }

        return null;
    }

    private function normalizeCompareToken(string $value): string
    {
        return strtolower(preg_replace('/[^A-Z0-9]+/i', '', $value) ?? $value);
    }

    private function isDirectRevealEnabled(): bool
    {
        return (bool) config('unlock.direct_reveal', true);
    }
}
