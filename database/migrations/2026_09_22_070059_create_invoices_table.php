<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
    Schema::create('invoices', function (Blueprint $table) {
    $table->id();

    $table->foreignId('merchant_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('customer_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('subscription_id')
        ->constrained()
        ->restrictOnDelete();

    $table->date('billing_period_start');

    $table->date('billing_period_end');

    $table->decimal('subtotal', 12, 2);

    $table->decimal('overage_total', 12, 2);

    $table->decimal('total', 12, 2);

    $table->string('status');

    $table->dateTime('issued_at')->nullable();

    $table->timestamps();

    $table->unique([
        'subscription_id',
        'billing_period_start',
        'billing_period_end'
    ],'invoice_subscription_period_uq');

    $table->index([
        'merchant_id',
        'billing_period_start'
    ],'invoice_merchant_period_idx');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
