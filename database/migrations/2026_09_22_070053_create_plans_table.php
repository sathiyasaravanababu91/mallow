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
        Schema::create('plans', function (Blueprint $table) {
	    $table->id();
 	    $table->foreignId('merchant_id')
        	->constrained()
	        ->cascadeOnDelete();

	    $table->string('name');
	    $table->decimal('base_price', 12, 2);
	    $table->string('billing_cycle');
	    $table->unsignedBigInteger('included_units');
	    $table->decimal('overage_rate', 12, 6);
	    $table->timestamps();
	    $table->index(['merchant_id', 'name']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
