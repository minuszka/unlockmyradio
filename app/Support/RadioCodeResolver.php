<?php

namespace App\Support;

use App\Models\RadioCode;
use Illuminate\Support\Collection;

class RadioCodeResolver
{
    public function normalizeSerial(string $serial): string
    {
        return strtoupper(trim($serial));
    }

    public function findCandidates(string $inputSerial): Collection
    {
        $serial = $this->normalizeSerial($inputSerial);

        $exact = RadioCode::query()
            ->where('serial', $serial)
            ->orderBy('brand')
            ->orderBy('car_make')
            ->get();

        if ($exact->isNotEmpty()) {
            return $exact;
        }

        $beckerLookupSerial = $this->extractBeckerLookupSerial($serial);
        if ($beckerLookupSerial === null) {
            return collect();
        }

        return RadioCode::query()
            ->where('brand', 'Becker')
            ->where('serial', $beckerLookupSerial)
            ->orderBy('car_make')
            ->get();
    }

    public function serialMatchesResult(string $inputSerial, RadioCode $result): bool
    {
        $serial = $this->normalizeSerial($inputSerial);

        if ($result->serial === $serial) {
            return true;
        }

        if (strcasecmp($result->brand, 'Becker') !== 0) {
            return false;
        }

        $beckerLookupSerial = $this->extractBeckerLookupSerial($serial);

        return $beckerLookupSerial !== null && $result->serial === $beckerLookupSerial;
    }

    public function extractBeckerLookupSerial(string $inputSerial): ?string
    {
        $serial = $this->normalizeSerial($inputSerial);
        $digits = preg_replace('/\D+/', '', $serial);

        $plainEightDigitSerial = $digits !== null
            && strlen($digits) === 8
            && preg_match('/^[\d\s-]+$/', $serial) === 1;

        // Common Becker input patterns:
        // - "BE1492 Y0010001"
        // - plain 8-digit serial like "51138970"
        $looksLikeBecker = preg_match('/BE\s*\d{3,5}/i', $serial) === 1
            || $plainEightDigitSerial;

        if (!$looksLikeBecker) {
            return null;
        }

        if ($digits === null || strlen($digits) < 4) {
            return null;
        }

        return substr($digits, -4);
    }
}
