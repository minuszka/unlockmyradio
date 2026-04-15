<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RadioCode;
use App\Support\RadioCodeResolver;
use App\Support\SerialClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class RadioCodeApiController extends Controller
{
    private const PRICE_USD = '2.99';
    private const PRICE_CENTS = 299;

    public function __construct(
        private readonly RadioCodeResolver $resolver,
        private readonly SerialClassifier $classifier
    )
    {
    }

    public function classifySerial(Request $request): JsonResponse
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

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'serial' => 'required|string|min:6|max:64',
        ]);

        $inputSerial = $this->resolver->normalizeSerial($validated['serial']);
        $results = $this->resolver->findCandidates($inputSerial);

        if ($results->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SERIAL_NOT_FOUND',
                    'message' => 'No code found for this serial number.',
                ],
            ], 404);
        }

        if ($results->count() === 1) {
            $result = $results->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'serial_input' => $inputSerial,
                    'serial_lookup' => $result->serial,
                    'found' => true,
                    'requires_selection' => false,
                    'radio_code_id' => $result->id,
                    'brand' => $result->brand,
                    'car_make' => $result->car_make,
                    'price_usd' => self::PRICE_USD,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'serial_input' => $inputSerial,
                'serial_lookup' => $results->first()->serial,
                'found' => true,
                'requires_selection' => true,
                'price_usd' => self::PRICE_USD,
                'options' => $results->map(fn (RadioCode $item): array => $this->formatOption($item))->values(),
            ],
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'serial' => 'required|string|min:6|max:64',
            'email' => 'required|email|max:100',
            'radio_code_id' => 'nullable|integer',
            'success_url' => [
                'nullable',
                'string',
                'max:2048',
                'regex:/^(https?:\/\/|[a-z][a-z0-9+\-.]*:\/\/).+/i',
            ],
            'cancel_url' => [
                'nullable',
                'string',
                'max:2048',
                'regex:/^(https?:\/\/|[a-z][a-z0-9+\-.]*:\/\/).+/i',
            ],
        ]);

        $inputSerial = $this->resolver->normalizeSerial($validated['serial']);

        if (isset($validated['radio_code_id'])) {
            $result = RadioCode::query()->find($validated['radio_code_id']);

            if (!$result || !$this->resolver->serialMatchesResult($inputSerial, $result)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_SELECTION',
                        'message' => 'Selected radio model does not match input serial.',
                    ],
                ], 422);
            }
        } else {
            $results = $this->resolver->findCandidates($inputSerial);

            if ($results->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'SERIAL_NOT_FOUND',
                        'message' => 'Serial not found.',
                    ],
                ], 404);
            }

            if ($results->count() > 1) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'MODEL_SELECTION_REQUIRED',
                        'message' => 'Multiple models found. Provide radio_code_id.',
                    ],
                    'data' => [
                        'serial_input' => $inputSerial,
                        'serial_lookup' => $results->first()->serial,
                        'options' => $results->map(fn (RadioCode $item): array => $this->formatOption($item))->values(),
                    ],
                ], 409);
            }

            $result = $results->first();
        }

        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STRIPE_NOT_CONFIGURED',
                    'message' => 'Stripe secret key is missing on server.',
                ],
            ], 500);
        }

        Stripe::setApiKey($secret);

        $successUrl = $validated['success_url'] ?? route('payment.success').'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $validated['cancel_url'] ?? route('home');

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
                        'unit_amount' => self::PRICE_CENTS,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'customer_email' => $validated['email'],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'serial_input' => $inputSerial,
                    'serial_lookup' => $result->serial,
                    'email' => $validated['email'],
                    'radio_code_id' => (string) $result->id,
                    'channel' => 'android',
                ],
            ]);
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STRIPE_SESSION_FAILED',
                    'message' => 'Unable to create checkout session.',
                ],
            ], 502);
        }

        $order = Order::query()->create([
            'serial' => $result->serial,
            'email' => $validated['email'],
            'stripe_session_id' => $session->id,
            'amount' => self::PRICE_USD,
            'currency' => 'USD',
            'status' => 'pending',
            'brand' => $result->brand,
            'car_make' => $result->car_make,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'session_id' => $session->id,
                'checkout_url' => $session->url,
                'expires_at' => $session->expires_at,
            ],
        ], 201);
    }

    public function paymentSuccess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string|max:255',
        ]);

        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STRIPE_NOT_CONFIGURED',
                    'message' => 'Stripe secret key is missing on server.',
                ],
            ], 500);
        }

        Stripe::setApiKey($secret);

        try {
            $session = Session::retrieve($validated['session_id']);
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STRIPE_SESSION_NOT_FOUND',
                    'message' => 'Checkout session not found.',
                ],
            ], 404);
        }

        if ($session->payment_status !== 'paid') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYMENT_NOT_COMPLETED',
                    'message' => 'Payment not completed yet.',
                ],
            ], 402);
        }

        $order = Order::query()->where('stripe_session_id', $session->id)->first();
        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_FOUND',
                    'message' => 'Order was not found for this session.',
                ],
            ], 404);
        }

        DB::transaction(function () use ($order, $session): void {
            $lockedOrder = Order::query()->lockForUpdate()->find($order->id);

            if (!$lockedOrder || ($lockedOrder->status === 'paid' && $lockedOrder->code_revealed !== null)) {
                return;
            }

            $result = $this->resolveRadioCodeForOrder($lockedOrder, $session);

            if (!$result) {
                Log::warning('API code resolution failed', [
                    'order_id' => $lockedOrder->id,
                    'session_id' => $session->id,
                    'order_serial' => $lockedOrder->serial,
                    'order_brand' => $lockedOrder->brand,
                    'order_car_make' => $lockedOrder->car_make,
                    'metadata_radio_code_id' => isset($session->metadata->radio_code_id) ? (string) $session->metadata->radio_code_id : null,
                    'metadata_serial_lookup' => isset($session->metadata->serial_lookup) ? (string) $session->metadata->serial_lookup : null,
                ]);

                return;
            }

            $lockedOrder->update([
                'status' => 'paid',
                'code_revealed' => $result->code,
                'stripe_payment_id' => is_string($session->payment_intent) ? $session->payment_intent : null,
            ]);
        });

        $order->refresh();

        if ($order->status !== 'paid' || $order->code_revealed === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CODE_REVEAL_FAILED',
                    'message' => 'Payment received but code reveal failed.',
                ],
            ], 500);
        }

        $displaySerial = isset($session->metadata->serial_input)
            ? (string) $session->metadata->serial_input
            : $order->serial;

        return response()->json([
            'success' => true,
            'data' => [
                'serial' => $displaySerial,
                'serial_lookup' => $order->serial,
                'brand' => $order->brand,
                'car_make' => $order->car_make,
                'code' => $order->code_revealed,
            ],
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

    private function formatOption(RadioCode $item): array
    {
        $lowerMake = strtolower($item->car_make);
        $hint = null;

        if (str_contains($lowerMake, '4 buttons')) $hint = 'Preset buttons: 1 2 3 4';
        if (str_contains($lowerMake, '5 buttons')) $hint = 'Preset buttons: 1 2 3 4 5';
        if (str_contains($lowerMake, '6 buttons')) $hint = 'Preset buttons: 1 2 3 4 5 6';
        if (str_contains($lowerMake, '8 buttons')) $hint = 'Preset buttons: 1 2 3 4 5 6 7 8';
        if (str_contains($lowerMake, '4-digit lookup')) $hint = 'Lookup uses last 4 digits from full serial';
        if (str_contains($lowerMake, 'vp1/vp2')) $hint = 'Lookup uses last 4 digits from A2C/A3C serial';

        return [
            'radio_code_id' => $item->id,
            'brand' => $item->brand,
            'car_make' => $item->car_make,
            'lookup_serial' => $item->serial,
            'hint' => $hint,
        ];
    }
}
