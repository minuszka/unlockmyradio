<?php

namespace App\Support;

use App\Models\RadioCode;
use Illuminate\Support\Collection;

class RadioCodeResolver
{
    private const CHRYSLER_5_PREFIXES = ['CHR55', 'CHR56'];
    private const CHRYSLER_4_PREFIXES = ['CHR4'];
    private const CONTINENTAL_4_PREFIXES = ['CONT4'];

    public function normalizeSerial(string $serial): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $serial) ?? $serial));
    }

    public function compactSerial(string $serial): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $serial) ?? $serial);
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
        $isTvpqn = str_starts_with($compact, 'TVPQN');

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

    private function sortCandidates(Collection $candidates): Collection
    {
        return $candidates
            ->unique('id')
            ->sortBy(static fn (RadioCode $item): string => strtolower($item->brand.'|'.$item->car_make.'|'.$item->serial))
            ->values();
    }
}
