<?php

namespace App\Console\Commands;

use App\Jobs\AggregateDailyUsage;
use Illuminate\Console\Command;

class AggregateDailyUsageCommand extends Command
{
    protected $signature = 'usage:aggregate {date?}';

    protected $description = 'Queue daily usage aggregation';

    public function handle(): int
    {
        $date = $this->argument('date') ?? now()->toDateString();

        AggregateDailyUsage::dispatch($date);

        $this->info("Daily usage aggregation queued for {$date}");

        return self::SUCCESS;
    }
}
