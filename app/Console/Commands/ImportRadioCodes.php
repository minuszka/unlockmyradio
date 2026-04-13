<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportRadioCodes extends Command
{
    protected $signature = 'import:radiocodes {path}';
    protected $description = 'Import radio codes from txt files';

    private array $brands = [
        // Philips
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
        // VAG
        'VWZ'   => ['brand' => 'VAG', 'car_make' => 'Volkswagen'],
        'AUZ'   => ['brand' => 'VAG', 'car_make' => 'Audi'],
        'SEZ'   => ['brand' => 'VAG', 'car_make' => 'Seat'],
        'SKZ'   => ['brand' => 'VAG', 'car_make' => 'Skoda'],
        // Grundig
        'FA'    => ['brand' => 'Grundig', 'car_make' => 'Fiat'],
        'DB'    => ['brand' => 'Grundig', 'car_make' => 'Mercedes-Benz'],
        'SK'    => ['brand' => 'Grundig', 'car_make' => 'Skoda'],
        'SE'    => ['brand' => 'Grundig', 'car_make' => 'Seat'],
        'YS'    => ['brand' => 'Grundig', 'car_make' => 'Saab'],
        'GR'    => ['brand' => 'Grundig', 'car_make' => 'Various'],
        // Delco/GM
        'GM'    => ['brand' => 'Delco',   'car_make' => 'General Motors'],
        // Ford
        'M'     => ['brand' => 'Ford',    'car_make' => 'Ford'],
        'V'     => ['brand' => 'Ford',    'car_make' => 'Ford'],
    ];

    private array $filenameMap = [
        // Grundig — specifikus előbb
        'fa_fiat'     => ['brand' => 'Grundig',     'car_make' => 'Fiat'],
        'db_mercedes' => ['brand' => 'Grundig',     'car_make' => 'Mercedes-Benz'],
        'sk_skoda'    => ['brand' => 'Grundig',     'car_make' => 'Skoda'],
        'se_seat'     => ['brand' => 'Grundig',     'car_make' => 'Seat'],
        'ys_saab'     => ['brand' => 'Grundig',     'car_make' => 'Saab'],
        'gr_grundig'  => ['brand' => 'Grundig',     'car_make' => 'Various'],
        'gm_opel'     => ['brand' => 'Grundig',     'car_make' => 'Opel/Vauxhall'],
        // Philips — specifikus előbb
        'ar_alfa'     => ['brand' => 'Philips',     'car_make' => 'Alfa Romeo'],
        'fif_fiat'    => ['brand' => 'Philips',     'car_make' => 'Fiat'],
        'fo_ford'     => ['brand' => 'Philips',     'car_make' => 'Ford'],
        'ho_honda'    => ['brand' => 'Philips',     'car_make' => 'Honda'],
        'mi610s'      => ['brand' => 'Philips',     'car_make' => 'Mitsubishi'],
        'ni_nissan'   => ['brand' => 'Philips',     'car_make' => 'Nissan'],
        'op_opel'     => ['brand' => 'Philips',     'car_make' => 'Opel'],
        'pe_peugeot'  => ['brand' => 'Philips',     'car_make' => 'Peugeot'],
        'ph_philips'  => ['brand' => 'Philips',     'car_make' => 'Philips'],
        'rg_rover'    => ['brand' => 'Philips',     'car_make' => 'Rover Group'],
        'rn_renault'  => ['brand' => 'Philips',     'car_make' => 'Renault'],
        'su68_suzuki' => ['brand' => 'Philips',     'car_make' => 'Suzuki'],
        'vo_volvo'    => ['brand' => 'Philips',     'car_make' => 'Volvo'],
        // Delco/GM — specifikus előbb
        'gm_codes'    => ['brand' => 'Delco/GM',    'car_make' => 'General Motors'],
        'cdr'         => ['brand' => 'Delco',       'car_make' => 'General Motors'],
        'gm'          => ['brand' => 'Delco',       'car_make' => 'General Motors'],
        // Többi
        'vag'         => ['brand' => 'VAG',         'car_make' => 'VW/Audi/Skoda/Seat'],
        'chrysler_4digit'   => ['brand' => 'Chrysler',    'car_make' => 'Chrysler/Dodge/Jeep (4-digit lookup)'],
        'chrysler_5_5digit' => ['brand' => 'Chrysler',    'car_make' => 'Chrysler/Dodge/Jeep (5 buttons)'],
        'chrysler_5_6digit' => ['brand' => 'Chrysler',    'car_make' => 'Chrysler/Dodge/Jeep (6 buttons)'],
        'chrysler'          => ['brand' => 'Chrysler',    'car_make' => 'Chrysler/Dodge/Jeep'],
        'continental_vp1_vp2' => ['brand' => 'Continental', 'car_make' => 'Fiat/Alfa/VAG (VP1/VP2)'],
        'continental'       => ['brand' => 'Continental', 'car_make' => 'Fiat/Alfa/VAG'],
        'becker_4btn' => ['brand' => 'Becker',      'car_make' => 'Mercedes-Benz (4 buttons)'],
        'becker_6btn' => ['brand' => 'Becker',      'car_make' => 'Mercedes-Benz (6 buttons)'],
        'becker_8btn' => ['brand' => 'Becker',      'car_make' => 'Mercedes-Benz (8 buttons)'],
        'becker'      => ['brand' => 'Becker',      'car_make' => 'Mercedes-Benz'],
        'renault'     => ['brand' => 'Renault',     'car_make' => 'Renault/Dacia'],
        'ford'        => ['brand' => 'Ford',        'car_make' => 'Ford'],
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
            $serial = strtoupper(trim($serial));
            $code   = trim($code);

            if (empty($serial) || empty($code)) continue;
            if (in_array($code, ['NONE', 'CODE'])) continue;

            $prefix = $this->detectPrefix($serial, $filename);

            $batch[] = [
                'brand'      => $brandInfo['brand'],
                'car_make'   => $brandInfo['car_make'],
                'prefix'     => $prefix,
                'serial'     => $serial,
                'code'       => $code,
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
        $lower = strtolower($filename);
        foreach ($this->filenameMap as $key => $info) {
            if (str_contains($lower, $key)) return $info;
        }

        foreach ($this->brands as $prefix => $info) {
            if (str_starts_with(strtoupper($filename), $prefix)) return $info;
        }

        return ['brand' => 'Unknown', 'car_make' => 'Unknown'];
    }

    private function detectPrefix(string $serial, string $filename = ''): string
    {
        $lowerFilename = strtolower($filename);
        if (str_contains($lowerFilename, 'becker_4btn')) {
            return 'B4BTN';
        }
        if (str_contains($lowerFilename, 'becker_6btn')) {
            return 'B6BTN';
        }
        if (str_contains($lowerFilename, 'becker_8btn')) {
            return 'B8BTN';
        }
        if (str_contains($lowerFilename, 'chrysler_4digit')) {
            return 'CHR4';
        }
        if (str_contains($lowerFilename, 'chrysler_5_5digit')) {
            return 'CHR55';
        }
        if (str_contains($lowerFilename, 'chrysler_5_6digit')) {
            return 'CHR56';
        }
        if (str_contains($lowerFilename, 'continental_vp1_vp2')) {
            return 'CONT4';
        }

        foreach (array_keys($this->brands) as $prefix) {
            if (str_starts_with($serial, $prefix)) return $prefix;
        }
        return substr($serial, 0, 3);
    }
}
