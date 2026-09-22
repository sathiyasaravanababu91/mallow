<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'subscription_plan_segment_id',
        'description',
        'units',
        'included_units',
        'overage_units',
        'unit_price',
        'amount',
    ];

    protected $casts = [
        'units' => 'integer',
        'included_units' => 'integer',
        'overage_units' => 'integer',
        'unit_price' => 'decimal:6',
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(
            SubscriptionPlanSegment::class,
            'subscription_plan_segment_id'
        );
    }
}
