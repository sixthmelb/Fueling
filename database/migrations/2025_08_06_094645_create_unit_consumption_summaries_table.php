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
        Schema::create('unit_consumption_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
            $table->date('summary_date');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('set null');
            
            // Aggregated data
            $table->integer('total_transactions')->default(0);
            $table->decimal('total_fuel_consumed', 10, 2)->default(0);
            $table->decimal('total_hour_meter_diff', 10, 2)->default(0);
            $table->decimal('total_odometer_diff', 10, 2)->default(0);
            
            // Average efficiency calculations
            $table->decimal('avg_fuel_per_hour', 8, 4)->nullable();
            $table->decimal('avg_fuel_per_km', 8, 4)->nullable();
            $table->decimal('avg_combined_efficiency', 8, 4)->nullable();
            
            // Min/Max for analysis
            $table->decimal('min_efficiency_per_hour', 8, 4)->nullable();
            $table->decimal('max_efficiency_per_hour', 8, 4)->nullable();
            $table->decimal('min_efficiency_per_km', 8, 4)->nullable();
            $table->decimal('max_efficiency_per_km', 8, 4)->nullable();
            
            // Period tracking
            $table->enum('period_type', ['Daily', 'Shift'])->default('Daily');
            $table->datetime('first_transaction_at')->nullable();
            $table->datetime('last_transaction_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['unit_id', 'summary_date', 'shift_id']);
            $table->index(['unit_id', 'summary_date']);
            $table->index(['summary_date', 'period_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_consumption_summaries');
    }
};