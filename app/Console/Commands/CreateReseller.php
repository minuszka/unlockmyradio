<?php

namespace App\Console\Commands;

use App\Models\Reseller;
use App\Models\ResellerApiKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateReseller extends Command
{
    protected $signature = 'reseller:create
        {name : Reseller display name}
        {--email= : Optional reseller email}
        {--credits= : Initial credits (default from config)}
        {--inactive : Create reseller in inactive state}';

    protected $description = 'Create reseller account and issue one API key';

    public function handle(): int
    {
        $defaultCredits = (int) config('reseller.test_default_credits', 50);
        $initialCredits = $this->option('credits') !== null
            ? max(0, (int) $this->option('credits'))
            : $defaultCredits;

        [$reseller, $plainKey] = DB::transaction(function () use ($initialCredits): array {
            $reseller = Reseller::query()->create([
                'name' => (string) $this->argument('name'),
                'email' => $this->option('email') !== null ? (string) $this->option('email') : null,
                'credits' => $initialCredits,
                'is_active' => !$this->option('inactive'),
            ]);

            $plainKey = Str::random(48);

            ResellerApiKey::query()->create([
                'reseller_id' => $reseller->id,
                'key_hash' => hash('sha256', $plainKey),
                'key_prefix' => substr($plainKey, 0, 12),
                'is_active' => true,
            ]);

            return [$reseller, $plainKey];
        });

        $this->info("Reseller created: #{$reseller->id} ({$reseller->name})");
        $this->line("Credits: {$reseller->credits}");
        $this->line("API key prefix: ".substr($plainKey, 0, 12));
        $this->newLine();
        $this->warn('Save this API key now. It will not be shown again:');
        $this->line($plainKey);

        return self::SUCCESS;
    }
}

