<?php

namespace App\Console\Commands;

use App\Jobs\GenerateCycleInvoices;
use Illuminate\Console\Command;

class GenerateCycleInvoicesCommand extends Command
{
    protected $signature = 'invoice:generate {date?}';

    protected $description = 'Queue cycle-end invoice generation';

    public function handle(): int
    {
        $date = $this->argument('date') ?? now()->toDateString();

        GenerateCycleInvoices::dispatch($date);

        $this->info(
            "Cycle-end invoice generation queued for {$date}"
        );

        return self::SUCCESS;
    }
}
