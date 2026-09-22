<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPlanSegment;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test normal monthly billing without overage.
     *
     * Base price: ₹100
     * Included usage: 1,000
     * Actual usage: 500
     *
     * Expected:
     * Subtotal      = ₹100
     * Overage       = ₹0
     * Total         = ₹100
     */
    public function test_normal_monthly_billing_without_overage(): void
    {
        $merchant = Merchant::create([
            'name' => 'Billing Test Merchant',
        ]);

        $plan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'Basic Plan',
            'base_price' => 100,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        $customer = Customer::create([
            'merchant_id' => $merchant->id,
            'name' => 'Billing Customer',
            'email' => 'billing@example.com',
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
            'usage_date' => '2026-09-10',
            'total_units' => 500,
        ]);

        $invoice = app(BillingService::class)->generateInvoice(
            $subscription,
            \Carbon\Carbon::parse('2026-09-01'),
            \Carbon\Carbon::parse('2026-09-30')
        );

        $this->assertEquals(100.00, (float) $invoice->subtotal);
        $this->assertEquals(0.00, (float) $invoice->overage_total);
        $this->assertEquals(100.00, (float) $invoice->total);

        $this->assertCount(1, $invoice->items);

        $item = $invoice->items->first();

        $this->assertEquals(500, $item->units);
        $this->assertEquals(1000, $item->included_units);
        $this->assertEquals(0, $item->overage_units);
    }

    /**
     * Test monthly billing with overage.
     *
     * Base price: ₹100
     * Included usage: 1,000
     * Actual usage: 1,500
     *
     * Overage:
     * 1,500 - 1,000 = 500
     *
     * Overage amount:
     * 500 × ₹0.02 = ₹10
     *
     * Expected:
     * Subtotal      = ₹100
     * Overage       = ₹10
     * Total         = ₹110
     */
    public function test_monthly_billing_with_overage(): void
    {
        $merchant = Merchant::create([
            'name' => 'Overage Test Merchant',
        ]);

        $plan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'Basic Plan',
            'base_price' => 100,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        $customer = Customer::create([
            'merchant_id' => $merchant->id,
            'name' => 'Overage Customer',
            'email' => 'overage@example.com',
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
            'usage_date' => '2026-09-10',
            'total_units' => 1500,
        ]);

        $invoice = app(BillingService::class)->generateInvoice(
            $subscription,
            \Carbon\Carbon::parse('2026-09-01'),
            \Carbon\Carbon::parse('2026-09-30')
        );

        $this->assertEquals(100.00, (float) $invoice->subtotal);
        $this->assertEquals(10.00, (float) $invoice->overage_total);
        $this->assertEquals(110.00, (float) $invoice->total);

        $item = $invoice->items->first();

        $this->assertEquals(1500, $item->units);
        $this->assertEquals(1000, $item->included_units);
        $this->assertEquals(500, $item->overage_units);
        $this->assertEquals(0.02, (float) $item->unit_price);
        $this->assertEquals(110.00, (float) $item->amount);
    }

    /**
     * Test mid-cycle subscription proration.
     *
     * Billing period:
     * September 1 - September 30 = 30 days
     *
     * Subscription starts:
     * September 16
     *
     * Active days:
     * September 16 - September 30 = 15 days
     *
     * Base price:
     * ₹100 × 15/30 = ₹50
     *
     * Included units:
     * 1,000 × 15/30 = 500
     *
     * Usage:
     * 400
     *
     * Therefore no overage.
     */
    public function test_mid_cycle_billing_is_prorated(): void
    {
        $merchant = Merchant::create([
            'name' => 'Proration Test Merchant',
        ]);

        $plan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'Proration Plan',
            'base_price' => 100,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        $customer = Customer::create([
            'merchant_id' => $merchant->id,
            'name' => 'Proration Customer',
            'email' => 'proration@example.com',
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => '2026-09-16 00:00:00',
            'ends_at' => null,
            'current_period_start' => '2026-09-16 00:00:00',
            'current_period_end' => '2026-09-30 23:59:59',
        ]);

        SubscriptionPlanSegment::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'starts_at' => '2026-09-16 00:00:00',
            'ends_at' => null,
            'base_price' => 100,
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        DailyUsage::create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-09-20',
            'total_units' => 400,
        ]);

        $invoice = app(BillingService::class)->generateInvoice(
            $subscription,
            \Carbon\Carbon::parse('2026-09-01'),
            \Carbon\Carbon::parse('2026-09-30')
        );

        $this->assertEquals(50.00, (float) $invoice->subtotal);
        $this->assertEquals(0.00, (float) $invoice->overage_total);
        $this->assertEquals(50.00, (float) $invoice->total);

        $item = $invoice->items->first();

        $this->assertEquals(400, $item->units);
        $this->assertEquals(500, $item->included_units);
        $this->assertEquals(0, $item->overage_units);
    }

    /**
     * Test a mid-cycle plan change.
     *
     * September 1 - September 15:
     * Basic Plan
     *
     * September 16 - September 30:
     * Premium Plan
     *
     * Basic:
     * ₹100 × 15/30 = ₹50
     *
     * Basic included:
     * 1,000 × 15/30 = 500
     *
     * Basic usage:
     * 800
     *
     * Basic overage:
     * 800 - 500 = 300
     *
     * Basic overage amount:
     * 300 × ₹0.02 = ₹6
     *
     * Premium:
     * ₹200 × 15/30 = ₹100
     *
     * Premium included:
     * 3,000 × 15/30 = 1,500
     *
     * Premium usage:
     * 1,000
     *
     * Premium overage:
     * 0
     *
     * Expected:
     * Subtotal      = ₹150
     * Overage       = ₹6
     * Total         = ₹156
     */
    public function test_mid_cycle_plan_change_creates_separate_invoice_items(): void
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
            'email' => 'planchange@example.com',
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

        $basicSegment = SubscriptionPlanSegment::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $basicPlan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-15 23:59:59',
            'base_price' => 100,
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        $premiumSegment = SubscriptionPlanSegment::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $premiumPlan->id,
            'starts_at' => '2026-09-16 00:00:00',
            'ends_at' => null,
            'base_price' => 200,
            'included_units' => 3000,
            'overage_rate' => 0.01,
        ]);

        DailyUsage::create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-09-10',
            'total_units' => 800,
        ]);

        DailyUsage::create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-09-20',
            'total_units' => 1000,
        ]);

        $invoice = app(BillingService::class)->generateInvoice(
            $subscription,
            \Carbon\Carbon::parse('2026-09-01'),
            \Carbon\Carbon::parse('2026-09-30')
        );

        $this->assertCount(2, $invoice->items);

        $items = $invoice->items
            ->sortBy('subscription_plan_segment_id')
            ->values();

        $this->assertEquals(
            $basicSegment->id,
            $items[0]->subscription_plan_segment_id
        );

        $this->assertEquals(
            $premiumSegment->id,
            $items[1]->subscription_plan_segment_id
        );

        /*
         * Verify Basic segment.
         */
        $this->assertEquals(800, $items[0]->units);
        $this->assertEquals(500, $items[0]->included_units);
        $this->assertEquals(300, $items[0]->overage_units);
        $this->assertEquals(0.02, (float) $items[0]->unit_price);
        $this->assertEquals(56.00, (float) $items[0]->amount);

        /*
         * Verify Premium segment.
         */
        $this->assertEquals(1000, $items[1]->units);
        $this->assertEquals(1500, $items[1]->included_units);
        $this->assertEquals(0, $items[1]->overage_units);
        $this->assertEquals(0.01, (float) $items[1]->unit_price);
        $this->assertEquals(100.00, (float) $items[1]->amount);

        /*
         * Invoice totals.
         */
        $this->assertEquals(150.00, (float) $invoice->subtotal);
        $this->assertEquals(6.00, (float) $invoice->overage_total);
        $this->assertEquals(156.00, (float) $invoice->total);
    }

    /**
     * Test invoice generation is idempotent.
     *
     * Calling the billing service twice for the same
     * subscription and billing period must return the
     * same invoice.
     *
     * Only one invoice should exist.
     */
    public function test_generating_same_invoice_twice_does_not_create_duplicate(): void
    {
        $merchant = Merchant::create([
            'name' => 'Invoice Idempotency Merchant',
        ]);

        $plan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'Basic Plan',
            'base_price' => 100,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        $customer = Customer::create([
            'merchant_id' => $merchant->id,
            'name' => 'Invoice Customer',
            'email' => 'invoice@example.com',
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

        SubscriptionPlanSegment::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => null,
            'base_price' => 100,
            'included_units' => 1000,
            'overage_rate' => 0.02,
        ]);

        $service = app(BillingService::class);

        $periodStart = \Carbon\Carbon::parse('2026-09-01');
        $periodEnd = \Carbon\Carbon::parse('2026-09-30');

        $invoice1 = $service->generateInvoice(
            $subscription,
            $periodStart,
            $periodEnd
        );

        $invoice2 = $service->generateInvoice(
            $subscription,
            $periodStart,
            $periodEnd
        );

        $this->assertEquals($invoice1->id, $invoice2->id);

        $this->assertDatabaseCount('invoices', 1);
    }
}
