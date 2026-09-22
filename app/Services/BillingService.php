<?php

namespace App\Services;

use App\Models\DailyUsage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Subscription;
use App\Models\SubscriptionPlanSegment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function generateInvoice(
        Subscription $subscription,
        Carbon $periodStart,
        Carbon $periodEnd
    ): Invoice {
        return DB::transaction(function () use (
            $subscription,
            $periodStart,
            $periodEnd
        ) {
            /*
             * Invoice creation is idempotent.
             *
             * If an invoice already exists for this subscription
             * and billing period, return it instead of creating
             * another invoice.
             */
            $existingInvoice = Invoice::where('subscription_id', $subscription->id)
                ->whereDate(
                    'billing_period_start',
                    $periodStart->toDateString()
                )
                ->whereDate(
                    'billing_period_end',
                    $periodEnd->toDateString()
                )
                ->first();

            if ($existingInvoice) {
                return $existingInvoice->load('items');
            }

            /*
             * Get all pricing segments that overlap
             * the requested billing period.
             */
            $segments = SubscriptionPlanSegment::query()
                ->with('plan')
                ->where('subscription_id', $subscription->id)
                ->where('starts_at', '<=', $periodEnd)
                ->where(function ($query) use ($periodStart) {
                    $query
                        ->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $periodStart);
                })
                ->orderBy('starts_at')
                ->get();

            /*
             * Number of days in the billing period.
             *
             * Example:
             * September 1 - September 30 = 30 days
             */
            $periodDays = $periodStart->startOfDay()
                ->diffInDays($periodEnd->startOfDay()) + 1;

            $subtotal = 0.00;
            $overageTotal = 0.00;

            $invoice = Invoice::create([
                'merchant_id' => $subscription->customer->merchant_id,
                'customer_id' => $subscription->customer_id,
                'subscription_id' => $subscription->id,
                'billing_period_start' => $periodStart->toDateString(),
                'billing_period_end' => $periodEnd->toDateString(),
                'subtotal' => 0,
                'overage_total' => 0,
                'total' => 0,
                'status' => 'issued',
                'issued_at' => now(),
            ]);

            foreach ($segments as $segment) {

                /*
                 * Determine the portion of the segment
                 * that falls inside the billing period.
                 */
                $segmentStart = Carbon::parse($segment->starts_at)
                    ->startOfDay();

                if ($segmentStart->lt($periodStart->copy()->startOfDay())) {
                    $segmentStart = $periodStart->copy()->startOfDay();
                }

                if ($segment->ends_at) {
                    $segmentEnd = Carbon::parse($segment->ends_at)
                        ->startOfDay();
                } else {
                    $segmentEnd = $periodEnd->copy()->startOfDay();
                }

                if ($segmentEnd->gt($periodEnd->copy()->startOfDay())) {
                    $segmentEnd = $periodEnd->copy()->startOfDay();
                }

                if ($segmentStart->gt($segmentEnd)) {
                    continue;
                }

                /*
                 * Inclusive number of active days.
                 *
                 * Example:
                 * Sept 1 - Sept 15 = 15 days
                 * Sept 16 - Sept 30 = 15 days
                 */
                $segmentDays = $segmentStart->diffInDays($segmentEnd) + 1;

                /*
                 * Proration factor.
                 */
                $prorationFactor = $segmentDays / $periodDays;

                /*
                 * Prorated base subscription charge.
                 */
                $proratedBasePrice = round(
                    (float) $segment->base_price * $prorationFactor,
                    2
                );

                /*
                 * Prorated included usage allowance.
                 */
                $proratedIncludedUnits = (int) floor(
                    (float) $segment->included_units * $prorationFactor
                );

                /*
                 * Get usage only for this segment's date range.
                 */
                $usage = DailyUsage::query()
                    ->where('customer_id', $subscription->customer_id)
                    ->whereBetween('usage_date', [
                        $segmentStart->toDateString(),
                        $segmentEnd->toDateString(),
                    ])
                    ->sum('total_units');

                $usage = (int) $usage;

                /*
                 * Calculate overage.
                 */
                $overageUnits = max(
                    0,
                    $usage - $proratedIncludedUnits
                );

                $overageAmount = round(
                    $overageUnits * (float) $segment->overage_rate,
                    2
                );

                /*
                 * IMPORTANT:
                 *
                 * subtotal = base subscription charges only.
                 * overageTotal = usage overage charges only.
                 */
                $subtotal += $proratedBasePrice;
                $overageTotal += $overageAmount;

                /*
                 * Invoice item amount represents:
                 *
                 * prorated base + segment overage
                 */
                $itemAmount = round(
                    $proratedBasePrice + $overageAmount,
                    2
                );

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'subscription_plan_segment_id' => $segment->id,
                    'description' => sprintf(
                        '%s plan - %d day(s)',
                        $segment->plan->name,
                        $segmentDays
                    ),
                    'units' => $usage,
                    'included_units' => $proratedIncludedUnits,
                    'overage_units' => $overageUnits,
                    'unit_price' => $segment->overage_rate,
                    'amount' => $itemAmount,
                ]);
            }

            /*
             * Final invoice total.
             */
            $total = round(
                $subtotal + $overageTotal,
                2
            );

            $invoice->update([
                'subtotal' => round($subtotal, 2),
                'overage_total' => round($overageTotal, 2),
                'total' => $total,
            ]);

            return $invoice->fresh('items');
        });
    }
}
