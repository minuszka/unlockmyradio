<?php

namespace App\Console\Commands;

use App\Models\Reseller;
use App\Models\ResellerCreditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AdjustResellerCredits extends Command
{
    protected $signature = 'reseller:credit
        {reseller_id : Reseller ID}
        {delta : Positive to add credits, negative to deduct}
        {--reason=manual_adjust : Adjustment reason label}';

    protected $description = 'Adjust reseller credit balance manually';

    public function handle(): int
    {
        $resellerId = (int) $this->argument('reseller_id');
        $delta = (int) $this->argument('delta');
        $reason = (string) $this->option('reason');

        if ($delta === 0) {
            $this->error('Delta cannot be 0.');
            return self::FAILURE;
        }

        $result = DB::transaction(function () use ($resellerId, $delta, $reason): ?array {
            $reseller = Reseller::query()->lockForUpdate()->find($resellerId);
            if (!$reseller) {
                return null;
            }

            $nextBalance = $reseller->credits + $delta;
            if ($nextBalance < 0) {
                $nextBalance = 0;
            }

            $appliedDelta = $nextBalance - $reseller->credits;

            $reseller->update([
                'credits' => $nextBalance,
            ]);

            ResellerCreditLog::query()->create([
                'reseller_id' => $reseller->id,
                'delta' => $appliedDelta,
                'balance_after' => $nextBalance,
                'reason' => $reason,
                'context' => [
                    'requested_delta' => $delta,
                    'command' => 'reseller:credit',
                ],
            ]);

            return [
                'reseller' => $reseller,
                'applied_delta' => $appliedDelta,
                'balance' => $nextBalance,
            ];
        });

        if ($result === null) {
            $this->error("Reseller #{$resellerId} not found.");
            return self::FAILURE;
        }

        /** @var Reseller $reseller */
        $reseller = $result['reseller'];
        $this->info("Reseller #{$reseller->id} ({$reseller->name}) updated.");
        $this->line("Applied delta: {$result['applied_delta']}");
        $this->line("New balance: {$result['balance']}");

        return self::SUCCESS;
    }
}

