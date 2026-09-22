<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPlanSegment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * Merchant
         */
        $merchant = Merchant::create([
            'name' => 'Mallow Demo Merchant',
        ]);

        /*
         * Plan
         */
        $plan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'Basic Plan',
            'base_price' => 100.00,
            'billing_cycle' => 'monthly',
            'included_units' => 10000,
            'overage_rate' => 0.02,
        ]);

        /*
         * Customer
         */
        $customer = Customer::create([
            'merchant_id' => $merchant->id,
            'name' => 'Demo Customer',
            'email' => 'demo@mallow.test',
        ]);

        /*
         * Subscription
         */
        $periodStart = Carbon::today()->startOfMonth();
        $periodEnd = Carbon::today()->endOfMonth();

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => $periodStart,
            'ends_at' => null,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
        ]);

        /*
         * Subscription Plan Segment
         */
        SubscriptionPlanSegment::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'starts_at' => $periodStart,
            'ends_at' => null,
            'base_price' => $plan->base_price,
            'included_units' => $plan->included_units,
            'overage_rate' => $plan->overage_rate,
        ]);

        $this->command->info('Demo data created successfully.');

        $this->command->info(
            "Merchant ID: {$merchant->id}"
        );

        $this->command->info(
            "Plan ID: {$plan->id}"
        );

        $this->command->info(
            "Customer ID: {$customer->id}"
        );

        $this->command->info(
            "Subscription ID: {$subscription->id}"
        );
    }
}
