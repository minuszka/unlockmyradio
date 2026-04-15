<?php

namespace App\Support;

use App\Models\RadioCode;
use Illuminate\Support\Collection;

class RadioCodeResolver
{
    private const CHRYSLER_5_PREFIXES = ['CHR55', 'CHR56'];
    private const CHRYSLER_4_PREFIXES = ['CHR4'];
    private const CONTINENTAL_4_PREFIXES = ['CONT4'];

    public function __construct(private readonly SerialClassifier $classifier)
    {
    }

    public function normalizeSerial(string $serial): string
    {
        return $this->classifier->normalizeInput($serial);
    }

    public function compactSerial(string $serial): string
    {
        return $this->classifier->compactInput($serial);
    }

    public function findCandidates(string $inputSerial): Collection
    {
        $serial = $this->normalizeSerial($inputSerial);
        $compact = $this->compactSerial($serial);

        $exact = $this->findExactCandidates($serial, $compact);
        if ($exact->isNotEmpty()) {
            return $exact;
        }

        $fallback = $this->findFallbackCandidates($serial, $compact);

        return $this->sortCandidates($fallback);
    }

    public function serialMatchesResult(string $inputSerial, RadioCode $result): bool
    {
        return $this->findCandidates($inputSerial)
            ->contains(static fn (RadioCode $item): bool => $item->id === $result->id);
    }

    private function findExactCandidates(string $serial, string $compact): Collection
    {
        $exactValues = array_values(array_unique(array_filter([$serial, $compact])));
        if ($exactValues === []) {
            return collect();
        }

        return $this->sortCandidates(
            RadioCode::query()->whereIn('serial', $exactValues)->get()
        );
    }

    private function findFallbackCandidates(string $serial, string $compact): Collection
    {
        $classification = $this->classifier->classify($serial);
        $family = $classification['family'] ?? '';

        if ($family === 'becker') {
            $lookup = $classification['lookup_serial'] ?? null;
            if (is_string($lookup) && $lookup !== '') {
                $becker = $this->queryByBrandAndSerial('Becker', $lookup);
                if ($becker->isNotEmpty()) {
                    return $becker;
                }
            }
        }

        if ($family === 'continental_vp') {
            $lookup = $classification['lookup_serial'] ?? null;
            if (is_string($lookup) && $lookup !== '') {
                $continental = $this->queryByBrandAndSerial('Continental', $lookup, self::CONTINENTAL_4_PREFIXES);
                if ($continental->isNotEmpty()) {
                    return $continental;
                }
            }
        }

        if ($family === 'chrysler_t') {
            $lookupCandidates = $classification['lookup_candidates'] ?? [];
            if (is_array($lookupCandidates) && $lookupCandidates !== []) {
                $last5 = isset($lookupCandidates[0]) ? (string) $lookupCandidates[0] : null;
                $last4 = isset($lookupCandidates[1]) ? (string) $lookupCandidates[1] : (isset($lookupCandidates[0]) ? (string) $lookupCandidates[0] : null);

                if ($last5 !== null && strlen($last5) === 5) {
                    $chrysler5 = $this->queryByBrandAndSerial('Chrysler', $last5, self::CHRYSLER_5_PREFIXES);
                    if ($chrysler5->isNotEmpty()) {
                        return $chrysler5;
                    }
                }

                if ($last4 !== null && strlen($last4) === 4) {
                    $chrysler4 = $this->queryByBrandAndSerial('Chrysler', $last4, self::CHRYSLER_4_PREFIXES);
                    $continental4 = $this->queryByBrandAndSerial('Continental', $last4, self::CONTINENTAL_4_PREFIXES);

                    $merged = $chrysler4->merge($continental4)->unique('id')->values();
                    if ($merged->isNotEmpty()) {
                        return $merged;
                    }
                }
            }
        }

        if ($family === 'fiat_bp_cm') {
            $lookup = $classification['lookup_serial'] ?? null;
            if (is_string($lookup) && $lookup !== '') {
                $bpCm = $this->queryBySerial($lookup);
                if ($bpCm->isNotEmpty()) {
                    return $bpCm;
                }
            }
        }

        if ($family === 'ford_m' || $family === 'ford_v' || $family === 'fiat_visteon_m') {
            $lookup = $classification['lookup_serial'] ?? null;
            if (is_string($lookup) && $lookup !== '') {
                $fordOrFiatM = $this->queryBySerial($lookup);
                if ($fordOrFiatM->isNotEmpty()) {
                    return $fordOrFiatM;
                }
            }
        }

        $beckerLookup = $this->extractBeckerLookupSerial($serial, $compact);
        if ($beckerLookup !== null) {
            $becker = $this->queryByBrandAndSerial('Becker', $beckerLookup);
            if ($becker->isNotEmpty()) {
                return $becker;
            }
        }

        $continentalLookup = $this->extractContinentalLookupSerial($serial, $compact);
        if ($continentalLookup !== null) {
            $continental = $this->queryByBrandAndSerial('Continental', $continentalLookup, self::CONTINENTAL_4_PREFIXES);
            if ($continental->isNotEmpty()) {
                return $continental;
            }
        }

        $chryslerLookups = $this->extractChryslerLookupSerials($serial, $compact);

        if (isset($chryslerLookups['last5'])) {
            $chrysler5 = $this->queryByBrandAndSerial('Chrysler', $chryslerLookups['last5'], self::CHRYSLER_5_PREFIXES);
            if ($chrysler5->isNotEmpty()) {
                return $chrysler5;
            }
        }

        if (isset($chryslerLookups['last4'])) {
            $chrysler4 = $this->queryByBrandAndSerial('Chrysler', $chryslerLookups['last4'], self::CHRYSLER_4_PREFIXES);
            $continental4 = $this->queryByBrandAndSerial('Continental', $chryslerLookups['last4'], self::CONTINENTAL_4_PREFIXES);

            $merged = $chrysler4->merge($continental4)->unique('id')->values();
            if ($merged->isNotEmpty()) {
                return $merged;
            }
        }

        return collect();
    }

    private function extractBeckerLookupSerial(string $serial, string $compact): ?string
    {
        $digits = preg_replace('/\D+/', '', $compact);
        $looksLikeBecker = preg_match('/^BE\d{4,}$/', $compact) === 1
            || preg_match('/^\d{8}$/', $compact) === 1;

        if (!$looksLikeBecker || $digits === null || strlen($digits) < 4) {
            return null;
        }

        return substr($digits, -4);
    }

    private function extractContinentalLookupSerial(string $serial, string $compact): ?string
    {
        $isA2CorA3C = preg_match('/^A[23]C[0-9A-Z]{10,}$/', $compact) === 1;
        $isTvpqn = preg_match('/^TVPQN[0-9A-Z]{5,}$/', $compact) === 1;

        if (!$isA2CorA3C && !$isTvpqn) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $compact);
        if ($digits !== null && strlen($digits) >= 4) {
            return substr($digits, -4);
        }

        return strlen($compact) >= 4 ? substr($compact, -4) : null;
    }

    private function extractChryslerLookupSerials(string $serial, string $compact): array
    {
        if (preg_match('/^T[A-Z0-9]{8,}$/', $compact) !== 1) {
            return [];
        }

        $digits = preg_replace('/\D+/', '', $compact);
        if ($digits === null || strlen($digits) < 4) {
            return [];
        }

        $lookups = [
            'last4' => substr($digits, -4),
        ];

        if (strlen($digits) >= 5) {
            $lookups['last5'] = substr($digits, -5);
        }

        return $lookups;
    }

    private function queryByBrandAndSerial(string $brand, string $serial, array $prefixes = []): Collection
    {
        if ($prefixes !== []) {
            $prefixed = RadioCode::query()
                ->where('brand', $brand)
                ->where('serial', $serial)
                ->whereIn('prefix', $prefixes)
                ->get();

            if ($prefixed->isNotEmpty()) {
                return $this->sortCandidates($prefixed);
            }
        }

        return $this->sortCandidates(
            RadioCode::query()
                ->where('brand', $brand)
                ->where('serial', $serial)
                ->get()
        );
    }

    private function queryBySerial(string $serial): Collection
    {
        return $this->sortCandidates(
            RadioCode::query()
                ->where('serial', $serial)
                ->get()
        );
    }

    private function sortCandidates(Collection $candidates): Collection
    {
        return $candidates
            ->unique('id')
            ->sortBy(static fn (RadioCode $item): string => strtolower($item->brand.'|'.$item->car_make.'|'.$item->serial))
            ->values();
    }
}
