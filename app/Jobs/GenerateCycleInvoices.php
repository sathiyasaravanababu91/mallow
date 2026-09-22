<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateCycleInvoices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $periodEnd;

    public function __construct(string $periodEnd)
    {
        $this->periodEnd = $periodEnd;
    }

    public function handle(BillingService $billingService): void
    {
        $periodEnd = Carbon::parse($this->periodEnd)->endOfDay();

        Subscription::query()
            ->where('status', 'active')
            ->whereDate('current_period_end', $periodEnd->toDateString())
            ->chunkById(100, function ($subscriptions) use (
                $billingService,
                $periodEnd
            ) {
                foreach ($subscriptions as $subscription) {
                    $periodStart = Carbon::parse(
                        $subscription->current_period_start
                    )->startOfDay();

                    $billingService->generateInvoice(
                        $subscription,
                        $periodStart,
                        $periodEnd
                    );
                }
            });
    }
}
