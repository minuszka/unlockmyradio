<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RadioCode;
use App\Models\Reseller;
use App\Models\ResellerApiKey;
use App\Models\ResellerCreditLog;
use App\Support\RadioCodeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResellerApiController extends Controller
{
    public function __construct(private readonly RadioCodeResolver $resolver)
    {
    }

    public function balance(Request $request): JsonResponse
    {
        $auth = $this->resolveReseller($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        [$reseller, $apiKey] = $auth;

        return response()->json([
            'success' => true,
            'data' => [
                'reseller_id' => $reseller->id,
                'name' => $reseller->name,
                'credits' => $reseller->credits,
                'key_prefix' => $apiKey->key_prefix,
            ],
        ]);
    }

    public function decode(Request $request): JsonResponse
    {
        $auth = $this->resolveReseller($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        [$reseller] = $auth;

        $validated = $request->validate([
            'serial' => 'required|string|min:6|max:64',
            'radio_code_id' => 'nullable|integer',
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
                        'message' => 'No code found for this serial number.',
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

        $consume = DB::transaction(function () use ($reseller, $inputSerial, $result): array {
            $lockedReseller = Reseller::query()->lockForUpdate()->find($reseller->id);

            if (!$lockedReseller || !$lockedReseller->is_active) {
                return [
                    'ok' => false,
                    'status' => 403,
                    'error' => [
                        'code' => 'RESELLER_INACTIVE',
                        'message' => 'Reseller account is inactive.',
                    ],
                ];
            }

            if ($lockedReseller->credits < 1) {
                return [
                    'ok' => false,
                    'status' => 402,
                    'error' => [
                        'code' => 'CREDITS_EXHAUSTED',
                        'message' => 'No credits remaining.',
                    ],
                ];
            }

            $nextBalance = $lockedReseller->credits - 1;

            $lockedReseller->update([
                'credits' => $nextBalance,
            ]);

            ResellerCreditLog::query()->create([
                'reseller_id' => $lockedReseller->id,
                'delta' => -1,
                'balance_after' => $nextBalance,
                'reason' => 'decode_success',
                'context' => [
                    'serial_input' => $inputSerial,
                    'serial_lookup' => $result->serial,
                    'radio_code_id' => $result->id,
                    'brand' => $result->brand,
                    'car_make' => $result->car_make,
                ],
            ]);

            return [
                'ok' => true,
                'remaining_credits' => $nextBalance,
            ];
        });

        if (!$consume['ok']) {
            return response()->json([
                'success' => false,
                'error' => $consume['error'],
            ], $consume['status']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'serial_input' => $inputSerial,
                'serial_lookup' => $result->serial,
                'brand' => $result->brand,
                'car_make' => $result->car_make,
                'code' => $result->code,
                'remaining_credits' => $consume['remaining_credits'],
                'charged_credits' => 1,
            ],
        ]);
    }

    private function resolveReseller(Request $request): array|JsonResponse
    {
        $token = $request->bearerToken() ?: $request->header('X-Api-Key');
        $token = is_string($token) ? trim($token) : '';

        if ($token === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_REQUIRED',
                    'message' => 'Provide Bearer token or X-Api-Key.',
                ],
            ], 401);
        }

        $apiKey = ResellerApiKey::query()
            ->with('reseller')
            ->where('key_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->first();

        if (!$apiKey || !$apiKey->reseller || !$apiKey->reseller->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_API_KEY',
                    'message' => 'API key is invalid or inactive.',
                ],
            ], 401);
        }

        $apiKey->forceFill([
            'last_used_at' => now(),
        ])->save();

        return [$apiKey->reseller, $apiKey];
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

