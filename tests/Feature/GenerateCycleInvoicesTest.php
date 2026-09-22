<?php

namespace Tests\Feature;

use App\Jobs\GenerateCycleInvoices;
use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPlanSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateCycleInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cycle_invoice_job_generates_invoice_for_ending_subscription(): void
    {
        $merchant = Merchant::create([
            'name' => 'Merchant A',
        ]);

        $plan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'Basic',
            'base_price' => 100,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        $customer = Customer::create([
            'merchant_id' => $merchant->id,
            'name' => 'Customer A',
            'email' => 'cycle@example.com',
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
            'current_period_start' => '2026-09-01 00:00:00',
            'current_period_end' => '2026-09-30 23:59:59',
        ]);

        SubscriptionPlanSegment::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => null,
            'base_price' => 100,
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        DailyUsage::create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-09-15',
            'total_units' => 1500,
        ]);

        $job = new GenerateCycleInvoices('2026-09-30');

        $job->handle(app(\App\Services\BillingService::class));

        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscription->id,
            'billing_period_start' => '2026-09-01',
            'billing_period_end' => '2026-09-30',
            'subtotal' => 100.00,
            'overage_total' => 10.00,
            'total' => 110.00,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'subscription_plan_segment_id' => $subscription
                ->segments()
                ->first()
                ->id,
            'units' => 1500,
            'included_units' => 1000,
            'overage_units' => 500,
        ]);
    }
}
