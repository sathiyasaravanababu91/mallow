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
    Schema::create('invoice_items', function (Blueprint $table) {
    $table->id();

    $table->foreignId('invoice_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('subscription_plan_segment_id')
        ->constrained()
        ->restrictOnDelete();

    $table->string('description');

    $table->unsignedBigInteger('units')->default(0);

    $table->unsignedBigInteger('included_units')->default(0);

    $table->unsignedBigInteger('overage_units')->default(0);

    $table->decimal('unit_price', 12, 6);

    $table->decimal('amount', 12, 2);

    $table->timestamps();

    $table->index('invoice_id');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
