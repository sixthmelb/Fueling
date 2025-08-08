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
        Schema::create('fuel_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 50)->unique();
            $table->foreignId('unit_id')->constrained('units')->onDelete('restrict');
            $table->foreignId('daily_session_id')->constrained('daily_sessions')->onDelete('restrict');
            
            // Polymorphic relation for fuel source (storage or truck)
            $table->morphs('fuel_source'); // creates fuel_source_type & fuel_source_id
            
            // Hour Meter & Odometer tracking
            $table->decimal('previous_hour_meter', 10, 2);
            $table->decimal('current_hour_meter', 10, 2);
            // MYSQL FIX: Remove computed columns for now, calculate in model
            
            $table->decimal('previous_odometer', 10, 2);
            $table->decimal('current_odometer', 10, 2);
            // MYSQL FIX: Remove computed columns for now, calculate in model
            
            // Fuel transaction details
            $table->decimal('fuel_amount', 8, 2)->comment('Fuel amount in liters');
            $table->decimal('source_level_before', 10, 2)->comment('Source fuel level before transaction');
            $table->decimal('source_level_after', 10, 2)->comment('Source fuel level after transaction');
            
            // Real-time consumption calculation
            $table->decimal('fuel_efficiency_per_hour', 8, 4)->nullable()->comment('L/hour');
            $table->decimal('fuel_efficiency_per_km', 8, 4)->nullable()->comment('L/km');
            $table->decimal('combined_efficiency', 8, 4)->nullable()->comment('Overall efficiency score');
            
            $table->datetime('transaction_datetime');
            $table->string('operator_name', 100);
            $table->text('notes')->nullable();
            
            // Metadata
            $table->datetime('calculated_at')->nullable();
            $table->timestamps();
            
            // MYSQL FIX: Indexes with custom shorter names (max 64 chars)
            $table->index(['unit_id', 'transaction_datetime'], 'idx_ft_unit_datetime');
            $table->index(['daily_session_id', 'transaction_datetime'], 'idx_ft_session_datetime');
            $table->index(['fuel_source_type', 'fuel_source_id', 'transaction_datetime'], 'idx_ft_source_datetime');
            $table->index(['transaction_datetime'], 'idx_ft_datetime');
            $table->index(['unit_id', 'daily_session_id'], 'idx_ft_unit_session');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_transactions');
    }
};