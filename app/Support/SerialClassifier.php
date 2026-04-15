<?php

namespace App\Support;

class SerialClassifier
{
    public function normalizeInput(string $input): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $input) ?? $input));
    }

    public function compactInput(string $input): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $input) ?? $input);
    }

    public function classify(string $input): array
    {
        $serial = $this->normalizeInput($input);
        $compact = $this->compactInput($serial);

        $match = $this->matchFiatBpCm($compact);
        if ($match !== null) {
            return $this->withInput($match, $serial, $compact);
        }

        $match = $this->matchFord($compact);
        if ($match !== null) {
            return $this->withInput($match, $serial, $compact);
        }

        $match = $this->matchVag($compact);
        if ($match !== null) {
            return $this->withInput($match, $serial, $compact);
        }

        $match = $this->matchContinentalVp($compact);
        if ($match !== null) {
            return $this->withInput($match, $serial, $compact);
        }

        $match = $this->matchChryslerT($compact);
        if ($match !== null) {
            return $this->withInput($match, $serial, $compact);
        }

        $match = $this->matchBecker($compact);
        if ($match !== null) {
            return $this->withInput($match, $serial, $compact);
        }

        $match = $this->matchGrundigFiat($compact);
        if ($match !== null) {
            return $this->withInput($match, $serial, $compact);
        }

        $match = $this->matchPhilipsFiat($compact);
        if ($match !== null) {
            return $this->withInput($match, $serial, $compact);
        }

        $shortNumeric = $this->matchShortNumeric($compact);
        if ($shortNumeric !== null) {
            return $this->withInput($shortNumeric, $serial, $compact);
        }

        return $this->withInput($this->result(
            family: 'unknown',
            confidence: 0,
            brandHint: null,
            lookupMode: 'exact',
            lookupSerial: null,
            matchedToken: null,
            compactInput: $compact
        ), $serial, $compact);
    }

    private function withInput(array $result, string $serialInput, string $compactInput): array
    {
        $result['serial_input'] = $serialInput;
        $result['compact_input'] = $compactInput;

        return $result;
    }

    private function matchFiatBpCm(string $compact): ?array
    {
        if (preg_match('/(?:815)?((?:BP|CM)[A-Z0-9]{1,12})/', $compact, $m) !== 1) {
            return null;
        }

        $token = $m[1];
        $isFull = strlen($token) === 14;

        return $this->result(
            family: 'fiat_bp_cm',
            confidence: $isFull ? 96 : 86,
            brandHint: 'Fiat Blaupunkt/Bosch',
            lookupMode: $isFull ? 'exact' : 'exact_pending',
            lookupSerial: $isFull ? $token : null,
            matchedToken: $token,
            compactInput: $compact
        );
    }

    private function matchFord(string $compact): ?array
    {
        if (preg_match('/(V\d{6})/', $compact, $vFull) === 1) {
            $token = $vFull[1];

            return $this->result(
                family: 'ford_v',
                confidence: 99,
                brandHint: 'Ford',
                lookupMode: 'exact',
                lookupSerial: $token,
                matchedToken: $token,
                compactInput: $compact
            );
        }

        if (preg_match('/(V\d{2,5})/', $compact, $vPartial) === 1) {
            $token = $vPartial[1];

            return $this->result(
                family: 'ford_v',
                confidence: 86,
                brandHint: 'Ford',
                lookupMode: 'exact_pending',
                lookupSerial: null,
                matchedToken: $token,
                compactInput: $compact
            );
        }

        $fiatVisteonContext = str_contains($compact, 'FIAT')
            || str_contains($compact, 'STILO')
            || str_contains($compact, 'BRAVO')
            || str_contains($compact, 'VISTEON');

        if (preg_match('/(M\d{6})/', $compact, $mFull) === 1) {
            $token = $mFull[1];

            return $this->result(
                family: $fiatVisteonContext ? 'fiat_visteon_m' : 'ford_m',
                confidence: $fiatVisteonContext ? 96 : 90,
                brandHint: $fiatVisteonContext ? 'Fiat Visteon' : 'Ford / Fiat Visteon',
                lookupMode: 'exact',
                lookupSerial: $token,
                matchedToken: $token,
                compactInput: $compact
            );
        }

        if (preg_match('/(M\d{2,5})/', $compact, $mPartial) === 1) {
            $token = $mPartial[1];

            return $this->result(
                family: $fiatVisteonContext ? 'fiat_visteon_m' : 'ford_m',
                confidence: $fiatVisteonContext ? 86 : 84,
                brandHint: $fiatVisteonContext ? 'Fiat Visteon' : 'Ford / Fiat Visteon',
                lookupMode: 'exact_pending',
                lookupSerial: null,
                matchedToken: $token,
                compactInput: $compact
            );
        }

        return null;
    }

    private function matchVag(string $compact): ?array
    {
        if (preg_match('/((?:VWZ|AUZ|SEZ|SKZ)[A-Z0-9]{6,})/', $compact, $m) !== 1) {
            return null;
        }

        $token = $m[1];

        return $this->result(
            family: 'vag',
            confidence: 98,
            brandHint: 'VAG',
            lookupMode: 'exact',
            lookupSerial: $token,
            matchedToken: $token,
            compactInput: $compact
        );
    }

    private function matchContinentalVp(string $compact): ?array
    {
        $token = null;
        $confidence = 0;

        if (preg_match('/(A[23]C[A-Z0-9]{1,})/', $compact, $m) === 1) {
            $token = $m[1];
            $confidence = strlen($token) >= 10 ? 96 : 86;
        } elseif (preg_match('/(TVPQN[A-Z0-9]{1,})/', $compact, $m) === 1) {
            $token = $m[1];
            $confidence = strlen($token) >= 8 ? 94 : 84;
        }

        if ($token === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $token) ?? '';
        $lookup = null;
        $lookupMode = 'last4_pending';
        $readyMinLength = str_starts_with($token, 'A2C') || str_starts_with($token, 'A3C') ? 19 : 8;

        if (strlen($digits) >= 4 && strlen($token) >= $readyMinLength) {
            $lookup = substr($digits, -4);
            $lookupMode = 'last4';
        }

        return $this->result(
            family: 'continental_vp',
            confidence: $confidence,
            brandHint: 'Continental',
            lookupMode: $lookupMode,
            lookupSerial: $lookup,
            matchedToken: $token,
            compactInput: $compact
        );
    }

    private function matchChryslerT(string $compact): ?array
    {
        if (preg_match('/(T[A-Z0-9]{8,})/', $compact, $m) !== 1) {
            return null;
        }

        $token = $m[1];
        $digits = preg_replace('/\D+/', '', $token) ?? '';
        if (strlen($digits) < 4) {
            return null;
        }

        $last4 = substr($digits, -4);
        $last5 = strlen($digits) >= 5 ? substr($digits, -5) : null;
        $lookupCandidates = $last5 !== null ? [$last5, $last4] : [$last4];

        return $this->result(
            family: 'chrysler_t',
            confidence: 91,
            brandHint: 'Chrysler',
            lookupMode: $last5 !== null ? 'last5_then_last4' : 'last4',
            lookupSerial: $last5 ?? $last4,
            matchedToken: $token,
            compactInput: $compact,
            lookupCandidates: $lookupCandidates
        );
    }

    private function matchBecker(string $compact): ?array
    {
        $token = null;
        $confidence = 0;

        if (preg_match('/(BE\d{4,})/', $compact, $m) === 1) {
            $token = $m[1];
            $confidence = 90;
        } elseif (preg_match('/^\d{8}$/', $compact) === 1) {
            $token = $compact;
            $confidence = 72;
        }

        if ($token === null) {
            return null;
        }

        $lookup = $this->tailDigitsOrChars($token, 4);
        if ($lookup === null) {
            return null;
        }

        return $this->result(
            family: 'becker',
            confidence: $confidence,
            brandHint: 'Becker',
            lookupMode: 'last4',
            lookupSerial: $lookup,
            matchedToken: $token,
            compactInput: $compact
        );
    }

    private function matchGrundigFiat(string $compact): ?array
    {
        if (preg_match('/(FA[A-Z0-9]{8,})/', $compact, $m) !== 1) {
            return null;
        }

        $token = $m[1];

        return $this->result(
            family: 'grundig_fiat',
            confidence: 94,
            brandHint: 'Grundig',
            lookupMode: 'exact',
            lookupSerial: $token,
            matchedToken: $token,
            compactInput: $compact
        );
    }

    private function matchPhilipsFiat(string $compact): ?array
    {
        if (preg_match('/(FIF[A-Z0-9]{8,}|FI710[A-Z0-9]{8,})/', $compact, $m) !== 1) {
            return null;
        }

        $token = $m[1];

        return $this->result(
            family: 'philips_fiat',
            confidence: 94,
            brandHint: 'Philips',
            lookupMode: 'exact',
            lookupSerial: $token,
            matchedToken: $token,
            compactInput: $compact
        );
    }

    private function matchShortNumeric(string $compact): ?array
    {
        if (preg_match('/^\d{4}$/', $compact) === 1) {
            return $this->result(
                family: 'short_4digit',
                confidence: 40,
                brandHint: null,
                lookupMode: 'last4',
                lookupSerial: $compact,
                matchedToken: $compact,
                compactInput: $compact
            );
        }

        if (preg_match('/^\d{5}$/', $compact) === 1) {
            return $this->result(
                family: 'short_5digit',
                confidence: 40,
                brandHint: null,
                lookupMode: 'last5',
                lookupSerial: $compact,
                matchedToken: $compact,
                compactInput: $compact
            );
        }

        return null;
    }

    private function tailDigitsOrChars(string $input, int $length): ?string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';
        if (strlen($digits) >= $length) {
            return substr($digits, -$length);
        }

        if (strlen($input) >= $length) {
            return substr($input, -$length);
        }

        return null;
    }

    /**
     * @param array<int,string> $lookupCandidates
     */
    private function result(
        string $family,
        int $confidence,
        ?string $brandHint,
        string $lookupMode,
        ?string $lookupSerial,
        ?string $matchedToken,
        string $compactInput,
        string $serialInput = '',
        array $lookupCandidates = []
    ): array {
        $serialInput = $serialInput !== '' ? $serialInput : $compactInput;

        if ($lookupSerial !== null && $lookupCandidates === []) {
            $lookupCandidates = [$lookupSerial];
        }

        return [
            'family' => $family,
            'confidence' => $confidence,
            'brand_hint' => $brandHint,
            'lookup_mode' => $lookupMode,
            'lookup_serial' => $lookupSerial,
            'lookup_candidates' => array_values(array_unique($lookupCandidates)),
            'matched_token' => $matchedToken,
            'serial_input' => $serialInput,
            'compact_input' => $compactInput,
        ];
    }
}
