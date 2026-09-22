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
    Schema::create('subscription_plan_segments', function (Blueprint $table) {
    $table->id();

    $table->foreignId('subscription_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('plan_id')
        ->constrained()
        ->restrictOnDelete();

    $table->dateTime('starts_at');

    $table->dateTime('ends_at')->nullable();

    $table->decimal('base_price', 12, 2);

    $table->unsignedBigInteger('included_units');

    $table->decimal('overage_rate', 12, 6);

    $table->timestamps();

    $table->index([
        'subscription_id',
        'starts_at',
        'ends_at'
    ],'sps_subscription_dates_idx');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_segments');
    }
};
