<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RadioCode;
use Illuminate\Support\Facades\DB;

class ImportRadioCodes extends Command
{
    protected $signature = 'import:radiocodes {path}';
    protected $description = 'Import radio codes from txt files';

    private array $brands = [
        'AR'    => ['brand' => 'Philips', 'car_make' => 'Alfa Romeo'],
        'FIF'   => ['brand' => 'Philips', 'car_make' => 'Fiat'],
        'FO'    => ['brand' => 'Philips', 'car_make' => 'Ford'],
        'HO'    => ['brand' => 'Philips', 'car_make' => 'Honda'],
        'MI'    => ['brand' => 'Philips', 'car_make' => 'Mitsubishi'],
        'NI'    => ['brand' => 'Philips', 'car_make' => 'Nissan'],
        'OP'    => ['brand' => 'Philips', 'car_make' => 'Opel'],
        'PE'    => ['brand' => 'Philips', 'car_make' => 'Peugeot'],
        'PH'    => ['brand' => 'Philips', 'car_make' => 'Philips'],
        'RG'    => ['brand' => 'Philips', 'car_make' => 'Rover Group'],
        'RN'    => ['brand' => 'Philips', 'car_make' => 'Renault'],
        'SU'    => ['brand' => 'Philips', 'car_make' => 'Suzuki'],
        'VO'    => ['brand' => 'Philips', 'car_make' => 'Volvo'],
        'VWZ'   => ['brand' => 'VAG',     'car_make' => 'Volkswagen'],
        'SKZ'   => ['brand' => 'VAG',     'car_make' => 'Skoda'],
        'AUZ'   => ['brand' => 'VAG',     'car_make' => 'Audi'],
        'SEZ'   => ['brand' => 'VAG',     'car_make' => 'Seat'],
        'GM'    => ['brand' => 'Delco',   'car_make' => 'General Motors'],
    ];

    public function handle(): void
    {
        $path = $this->argument('path');

        if (!is_dir($path)) {
            $this->error("Directory not found: $path");
            return;
        }

        $files = glob("$path/*.txt");
        $this->info("Found " . count($files) . " files.");

        foreach ($files as $file) {
            $this->importFile($file);
        }

        $this->info("Import complete!");
    }

    private function importFile(string $file): void
    {
        $filename = basename($file, '.txt');
        $this->info("Importing: $filename");

        $brandInfo = $this->detectBrand($filename);
        $batch = [];
        $count = 0;

        $handle = fopen($file, 'r');
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = preg_split('/\s+/', $line, 2);
            if (count($parts) !== 2) continue;

            [$serial, $code] = $parts;
            $serial = trim($serial);
            $code = trim($code);

            if (empty($serial) || empty($code)) continue;

            $prefix = $this->detectPrefix($serial);

            $batch[] = [
                'brand'    => $brandInfo['brand'],
                'car_make' => $brandInfo['car_make'],
                'prefix'   => $prefix,
                'serial'   => strtoupper($serial),
                'code'     => $code,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) >= 1000) {
                DB::table('radio_codes')->insertOrIgnore($batch);
                $count += count($batch);
                $batch = [];
                $this->line("  Inserted $count records...");
            }
        }
        fclose($handle);

        if (!empty($batch)) {
            DB::table('radio_codes')->insertOrIgnore($batch);
            $count += count($batch);
        }

        $this->info("  Done: $count records from $filename");
    }

    private function detectBrand(string $filename): array
    {
        $map = [
            'vag'         => ['brand' => 'VAG',       'car_make' => 'VW/Audi/Skoda/Seat'],
            'gm'          => ['brand' => 'Delco/GM',  'car_make' => 'General Motors'],
            'renault'     => ['brand' => 'Renault',   'car_make' => 'Renault/Dacia'],
            'chrysler'    => ['brand' => 'Chrysler',  'car_make' => 'Chrysler/Dodge/Jeep'],
            'continental' => ['brand' => 'Continental','car_make' => 'Fiat/Alfa/VAG'],
            'grundig'     => ['brand' => 'Grundig',   'car_make' => 'Various'],
            'becker'      => ['brand' => 'Becker',    'car_make' => 'Mercedes-Benz'],
            'cdr'         => ['brand' => 'Delco',     'car_make' => 'General Motors'],
        ];

        $lower = strtolower($filename);
        foreach ($map as $key => $info) {
            if (str_contains($lower, $key)) return $info;
        }

        // Philips prefix detektálás
        foreach ($this->brands as $prefix => $info) {
            if (str_starts_with(strtoupper($filename), $prefix)) return $info;
        }

        return ['brand' => 'Unknown', 'car_make' => 'Unknown'];
    }

    private function detectPrefix(string $serial): string
    {
        foreach (array_keys($this->brands) as $prefix) {
            if (str_starts_with(strtoupper($serial), $prefix)) return $prefix;
        }
        return substr($serial, 0, 3);
    }
}

