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
    Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();

    $table->foreignId('customer_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('plan_id')
        ->constrained()
        ->restrictOnDelete();

    $table->string('status');

    $table->dateTime('starts_at');

    $table->dateTime('ends_at')->nullable();

    $table->dateTime('current_period_start');

    $table->dateTime('current_period_end');

    $table->timestamps();

    $table->index(['customer_id', 'status']);

    $table->index([
        'current_period_start',
        'current_period_end'
    ]);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
