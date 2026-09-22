<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPlanSegment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubscriptionPlanChangeService
{
    public function changePlan(
        Subscription $subscription,
        Plan $newPlan,
        Carbon $effectiveAt
    ): SubscriptionPlanSegment {
        return DB::transaction(function () use (
            $subscription,
            $newPlan,
            $effectiveAt
        ) {
            $subscription->load('customer', 'plan');

            // Make sure the new plan belongs to the same merchant.
            if ($newPlan->merchant_id !== $subscription->customer->merchant_id) {
                throw new InvalidArgumentException(
                    'The new plan must belong to the same merchant as the subscription.'
                );
            }

            $subscriptionStart = Carbon::parse($subscription->starts_at);

            if ($effectiveAt->lt($subscriptionStart)) {
                throw new InvalidArgumentException(
                    'Plan change cannot happen before the subscription starts.'
                );
            }

            if (
                $subscription->ends_at &&
                $effectiveAt->gt(Carbon::parse($subscription->ends_at))
            ) {
                throw new InvalidArgumentException(
                    'Plan change cannot happen after the subscription ends.'
                );
            }

            // Do not allow a backdated change that would overlap
            // an already-created future plan segment.
            $futureSegmentExists = SubscriptionPlanSegment::query()
                ->where('subscription_id', $subscription->id)
                ->where('starts_at', '>', $effectiveAt)
                ->exists();

            if ($futureSegmentExists) {
                throw new InvalidArgumentException(
                    'Plan change cannot be backdated across an existing future plan segment.'
                );
            }

            $currentSegment = SubscriptionPlanSegment::query()
                ->where('subscription_id', $subscription->id)
                ->where('starts_at', '<=', $effectiveAt)
                ->where(function ($query) use ($effectiveAt) {
                    $query
                        ->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $effectiveAt);
                })
                ->orderByDesc('starts_at')
                ->first();

            if (!$currentSegment) {
                throw new InvalidArgumentException(
                    'No active subscription plan segment was found for the requested change time.'
                );
            }

            // No change required.
            if ($currentSegment->plan_id === $newPlan->id) {
                return $currentSegment;
            }

            // Close the existing historical segment immediately
            // before the new plan becomes active.
            $oldSegmentEnd = $effectiveAt->copy()->subSecond();

            $currentSegment->update([
                'ends_at' => $oldSegmentEnd,
            ]);

            // Create the new historical pricing segment.
            $newSegment = SubscriptionPlanSegment::create([
                'subscription_id' => $subscription->id,
                'plan_id' => $newPlan->id,
                'starts_at' => $effectiveAt,
                'ends_at' => null,
                'base_price' => $newPlan->base_price,
                'included_units' => $newPlan->included_units,
                'overage_rate' => $newPlan->overage_rate,
            ]);

            // Update the subscription's current plan.
            $subscription->update([
                'plan_id' => $newPlan->id,
            ]);

            return $newSegment;
        });
    }
}
