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
    Schema::create('usage_events', function (Blueprint $table) {
    $table->id();

    $table->foreignId('merchant_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('customer_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('subscription_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('subscription_plan_segment_id')
        ->constrained()
        ->restrictOnDelete();

    $table->string('idempotency_key', 128);

    $table->date('usage_date');

    $table->unsignedBigInteger('units');

    $table->timestamps();

    $table->unique([
        'customer_id',
        'idempotency_key'
    ]);

    $table->index([
        'merchant_id',
        'usage_date'
    ]);

    $table->index([
        'customer_id',
        'usage_date'
    ]);

    $table->index([
        'subscription_id',
        'usage_date'
    ]);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
