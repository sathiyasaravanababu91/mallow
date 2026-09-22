<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsageRequest;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionPlanSegment;
use App\Models\UsageEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UsageController extends Controller
{
    public function store(StoreUsageRequest $request): JsonResponse
    {
        $data = $request->validated();

        /*
         * 1. Verify that the customer belongs to the merchant.
         */
        $customer = Customer::query()
            ->where('id', $data['customer_id'])
            ->where('merchant_id', $data['merchant_id'])
            ->first();

        if (!$customer) {
            return response()->json([
                'message' => 'Customer does not belong to this merchant.',
            ], 422);
        }

        /*
         * 2. Verify that the subscription belongs to the customer.
         */
        $subscription = Subscription::query()
            ->where('id', $data['subscription_id'])
            ->where('customer_id', $customer->id)
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'Subscription does not belong to this customer.',
            ], 422);
        }

        /*
         * 3. Verify subscription is active for the usage date.
         */
        $usageDate = $data['usage_date'];

        if ($usageDate < $subscription->starts_at->toDateString()) {
            return response()->json([
                'message' => 'Usage date is before the subscription start date.',
            ], 422);
        }

        if (
            $subscription->ends_at &&
            $usageDate > $subscription->ends_at->toDateString()
        ) {
            return response()->json([
                'message' => 'Usage date is after the subscription end date.',
            ], 422);
        }

        /*
         * 4. Find the pricing segment applicable to this usage date.
         */
        $segment = SubscriptionPlanSegment::query()
            ->where('subscription_id', $subscription->id)
            ->where('starts_at', '<=', $usageDate)
            ->where(function ($query) use ($usageDate) {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $usageDate);
            })
            ->orderByDesc('starts_at')
            ->first();

        if (!$segment) {
            return response()->json([
                'message' => 'No subscription plan segment found for this usage date.',
            ], 422);
        }

        /*
         * 5. Insert the usage event.
         *
         * The database UNIQUE constraint on
         * customer_id + idempotency_key protects against
         * duplicate concurrent requests.
         */
        try {
            $usageEvent = DB::transaction(function () use (
                $data,
                $customer,
                $subscription,
                $segment
            ) {
                return UsageEvent::create([
                    'merchant_id' => $customer->merchant_id,
                    'customer_id' => $customer->id,
                    'subscription_id' => $subscription->id,
                    'subscription_plan_segment_id' => $segment->id,
                    'idempotency_key' => $data['idempotency_key'],
                    'usage_date' => $data['usage_date'],
                    'units' => $data['units'],
                ]);
            });
        } catch (\Illuminate\Database\QueryException $exception) {

            /*
             * Duplicate idempotency key.
             */
            if ($exception->getCode() === '23000') {
                $existing = UsageEvent::query()
                    ->where('customer_id', $customer->id)
                    ->where(
                        'idempotency_key',
                        $data['idempotency_key']
                    )
                    ->first();

                if ($existing) {
                    return response()->json([
                        'message' => 'Usage event already exists.',
                        'idempotent' => true,
                        'data' => $existing,
                    ], 200);
                }
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Usage recorded successfully.',
            'idempotent' => false,
            'data' => $usageEvent,
        ], 201);
    }
}
