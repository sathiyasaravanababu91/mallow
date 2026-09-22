<?php

namespace App\Jobs;

use App\Models\DailyUsage;
use App\Models\UsageEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AggregateDailyUsage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $usageDate;

    public function __construct(string $usageDate)
    {
        $this->usageDate = $usageDate;
    }

    public function handle(): void
    {
        UsageEvent::query()
            ->whereDate('usage_date', $this->usageDate)
            ->orderBy('id')
            ->chunkById(1000, function ($events) {

                $customerIds = $events
                    ->pluck('customer_id')
                    ->unique()
                    ->values();

                if ($customerIds->isEmpty()) {
                    return;
                }

                $totals = UsageEvent::query()
                    ->select(
                        'merchant_id',
                        'customer_id',
                        'usage_date',
                        DB::raw('SUM(units) as total_units')
                    )
                    ->whereDate('usage_date', $this->usageDate)
                    ->whereIn('customer_id', $customerIds)
                    ->groupBy(
                        'merchant_id',
                        'customer_id',
                        'usage_date'
                    )
                    ->get();

                foreach ($totals as $total) {
                    DailyUsage::updateOrCreate(
                        [
                            'customer_id' => $total->customer_id,
                            'usage_date' => $total->usage_date,
                        ],
                        [
                            'merchant_id' => $total->merchant_id,
                            'total_units' => $total->total_units,
                        ]
                    );
                }
            });
    }
}
