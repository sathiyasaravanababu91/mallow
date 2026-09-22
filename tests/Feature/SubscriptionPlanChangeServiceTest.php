<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPlanSegment;
use App\Services\SubscriptionPlanChangeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanChangeServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a mid-cycle plan change:
     *
     * 1. Closes the old segment.
     * 2. Creates a new segment.
     * 3. Copies pricing from the new plan.
     * 4. Preserves historical pricing.
     */

public function test_plan_change_updates_subscription_current_plan(): void
{
    $merchant = Merchant::create([
        'name' => 'Merchant A',
    ]);

    $basicPlan = Plan::create([
        'merchant_id' => $merchant->id,
        'name' => 'Basic',
        'base_price' => 100,
        'billing_cycle' => 'monthly',
        'included_units' => 1000,
        'overage_rate' => 0.02,
    ]);

    $premiumPlan = Plan::create([
        'merchant_id' => $merchant->id,
        'name' => 'Premium',
        'base_price' => 200,
        'billing_cycle' => 'monthly',
        'included_units' => 3000,
        'overage_rate' => 0.01,
    ]);

    $customer = Customer::create([
        'merchant_id' => $merchant->id,
        'name' => 'Test Customer',
        'email' => 'current-plan@example.com',
    ]);

    $subscription = Subscription::create([
        'customer_id' => $customer->id,
        'plan_id' => $basicPlan->id,
        'status' => 'active',
        'starts_at' => '2026-09-01 00:00:00',
        'ends_at' => '2026-09-30 23:59:59',
        'current_period_start' => '2026-09-01 00:00:00',
        'current_period_end' => '2026-09-30 23:59:59',
    ]);

    SubscriptionPlanSegment::create([
        'subscription_id' => $subscription->id,
        'plan_id' => $basicPlan->id,
        'starts_at' => '2026-09-01 00:00:00',
        'ends_at' => null,
        'base_price' => 100,
        'included_units' => 1000,
        'overage_rate' => 0.02,
    ]);

    $service = app(SubscriptionPlanChangeService::class);

    $service->changePlan(
        $subscription,
        $premiumPlan,
        Carbon::parse('2026-09-16 00:00:00')
    );

    $subscription->refresh();

    $this->assertSame(
        $premiumPlan->id,
        $subscription->plan_id
    );
}

    public function test_mid_cycle_plan_change_creates_new_historical_segment(): void
    {
        $merchant = Merchant::create([
            'name' => 'Plan Change Merchant',
        ]);

        $basicPlan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'Basic Plan',
            'base_price' => 100,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        $premiumPlan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'Premium Plan',
            'base_price' => 200,
            'billing_cycle' => 'monthly',
            'included_units' => 3000,
            'overage_rate' => 0.01,
        ]);

        $customer = Customer::create([
            'merchant_id' => $merchant->id,
            'name' => 'Plan Change Customer',
            'email' => 'plan-change@example.com',
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $premiumPlan->id,
            'status' => 'active',
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => null,
            'current_period_start' => '2026-09-01 00:00:00',
            'current_period_end' => '2026-09-30 23:59:59',
        ]);

        $oldSegment = SubscriptionPlanSegment::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $basicPlan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => null,
            'base_price' => 100,
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        $effectiveAt = Carbon::parse('2026-09-16 00:00:00');

        $newSegment = app(SubscriptionPlanChangeService::class)
            ->changePlan(
                $subscription,
                $premiumPlan,
                $effectiveAt
            );

        /*
         * Old segment should end immediately before
         * the new segment starts.
         */
        $oldSegment->refresh();

        $this->assertEquals(
            '2026-09-15 23:59:59',
            $oldSegment->ends_at->format('Y-m-d H:i:s')
        );

        /*
         * New segment should start exactly at the
         * requested effective time.
         */
        $this->assertEquals(
            '2026-09-16 00:00:00',
            $newSegment->starts_at->format('Y-m-d H:i:s')
        );

        /*
         * New segment must use the new plan.
         */
        $this->assertEquals(
            $premiumPlan->id,
            $newSegment->plan_id
        );

        /*
         * Pricing must be copied from the new plan.
         */
        $this->assertEquals(
            200.00,
            (float) $newSegment->base_price
        );

        $this->assertEquals(
            3000,
            $newSegment->included_units
        );

        $this->assertEquals(
            0.01,
            (float) $newSegment->overage_rate
        );

        /*
         * We should now have exactly two segments.
         */
        $this->assertDatabaseCount(
            'subscription_plan_segments',
            2
        );
    }

    /**
     * Test that a plan belonging to another merchant
     * cannot be assigned to the subscription.
     */
    public function test_plan_change_rejects_plan_from_another_merchant(): void
    {
        $merchant1 = Merchant::create([
            'name' => 'Merchant One',
        ]);

        $merchant2 = Merchant::create([
            'name' => 'Merchant Two',
        ]);

        $basicPlan = Plan::create([
            'merchant_id' => $merchant1->id,
            'name' => 'Basic Plan',
            'base_price' => 100,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        $otherMerchantPlan = Plan::create([
            'merchant_id' => $merchant2->id,
            'name' => 'Other Merchant Plan',
            'base_price' => 500,
            'billing_cycle' => 'monthly',
            'included_units' => 5000,
            'overage_rate' => 0.01,
        ]);

        $customer = Customer::create([
            'merchant_id' => $merchant1->id,
            'name' => 'Customer',
            'email' => 'customer@example.com',
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $basicPlan->id,
            'status' => 'active',
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => null,
            'current_period_start' => '2026-09-01 00:00:00',
            'current_period_end' => '2026-09-30 23:59:59',
        ]);

        SubscriptionPlanSegment::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $basicPlan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => null,
            'base_price' => 100,
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(SubscriptionPlanChangeService::class)->changePlan(
            $subscription,
            $otherMerchantPlan,
            Carbon::parse('2026-09-16 00:00:00')
        );
    }
}
