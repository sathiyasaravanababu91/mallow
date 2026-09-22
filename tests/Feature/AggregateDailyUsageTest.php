<?php

namespace Tests\Feature;

use App\Jobs\AggregateDailyUsage;
use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPlanSegment;
use App\Models\UsageEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AggregateDailyUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_usage_is_aggregated_correctly(): void
    {
        $merchant = Merchant::create([
            'name' => 'Test Merchant',
        ]);

        $plan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'Test Plan',
            'base_price' => 100,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        $customer = Customer::create([
            'merchant_id' => $merchant->id,
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => null,
            'current_period_start' => '2026-09-01 00:00:00',
            'current_period_end' => '2026-09-30 23:59:59',
        ]);

        $segment = SubscriptionPlanSegment::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => null,
            'base_price' => 100,
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        UsageEvent::create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_segment_id' => $segment->id,
            'idempotency_key' => 'test-usage-1',
            'usage_date' => '2026-09-22',
            'units' => 100,
        ]);

        UsageEvent::create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_segment_id' => $segment->id,
            'idempotency_key' => 'test-usage-2',
            'usage_date' => '2026-09-22',
            'units' => 250,
        ]);

        UsageEvent::create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_segment_id' => $segment->id,
            'idempotency_key' => 'test-usage-3',
            'usage_date' => '2026-09-22',
            'units' => 50,
        ]);

        (new AggregateDailyUsage('2026-09-22'))->handle();

        $dailyUsage = DailyUsage::where('customer_id', $customer->id)
            ->where('usage_date', '2026-09-22')
            ->first();

        $this->assertNotNull($dailyUsage);
        $this->assertEquals(400, $dailyUsage->total_units);
    }

    public function test_running_aggregation_twice_does_not_double_count(): void
    {
        $merchant = Merchant::create([
            'name' => 'Test Merchant',
        ]);

        $plan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'Test Plan',
            'base_price' => 100,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        $customer = Customer::create([
            'merchant_id' => $merchant->id,
            'name' => 'Test Customer',
            'email' => 'retry@example.com',
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => null,
            'current_period_start' => '2026-09-01 00:00:00',
            'current_period_end' => '2026-09-30 23:59:59',
        ]);

        $segment = SubscriptionPlanSegment::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => null,
            'base_price' => 100,
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        UsageEvent::create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_segment_id' => $segment->id,
            'idempotency_key' => 'retry-test-1',
            'usage_date' => '2026-09-22',
            'units' => 100,
        ]);

        UsageEvent::create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_segment_id' => $segment->id,
            'idempotency_key' => 'retry-test-2',
            'usage_date' => '2026-09-22',
            'units' => 200,
        ]);

        (new AggregateDailyUsage('2026-09-22'))->handle();

        (new AggregateDailyUsage('2026-09-22'))->handle();

        $dailyUsage = DailyUsage::where('customer_id', $customer->id)
            ->where('usage_date', '2026-09-22')
            ->first();

        $this->assertNotNull($dailyUsage);
        $this->assertEquals(300, $dailyUsage->total_units);
    }
}
