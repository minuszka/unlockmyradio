<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RadioCode;
use App\Support\RadioCodeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class RadioCodeApiController extends Controller
{
    private const PRICE_USD = '2.99';
    private const PRICE_CENTS = 299;

    public function __construct(private readonly RadioCodeResolver $resolver)
    {
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'serial' => 'required|string|min:4|max:40',
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
                'options' => $results->map(static function (RadioCode $item): array {
                    return [
                        'radio_code_id' => $item->id,
                        'brand' => $item->brand,
                        'car_make' => $item->car_make,
                        'lookup_serial' => $item->serial,
                    ];
                })->values(),
            ],
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'serial' => 'required|string|min:4|max:40',
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
                        'options' => $results->map(static function (RadioCode $item): array {
                            return [
                                'radio_code_id' => $item->id,
                                'brand' => $item->brand,
                                'car_make' => $item->car_make,
                                'lookup_serial' => $item->serial,
                            ];
                        })->values(),
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

            if (!$lockedOrder || $lockedOrder->status === 'paid') {
                return;
            }

            $result = RadioCode::query()
                ->where('serial', $lockedOrder->serial)
                ->where('brand', $lockedOrder->brand)
                ->where('car_make', $lockedOrder->car_make)
                ->first();

            if (!$result) {
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

        return response()->json([
            'success' => true,
            'data' => [
                'serial' => $order->serial,
                'brand' => $order->brand,
                'car_make' => $order->car_make,
                'code' => $order->code_revealed,
            ],
        ]);
    }
}

