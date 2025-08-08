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
        Schema::create('fuel_consumption_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_type_id')->constrained('unit_types')->onDelete('cascade');
            
            // Consumption rates
            $table->decimal('consumption_per_hour', 8, 2)->comment('Liters per hour');
            $table->decimal('consumption_per_km', 8, 2)->comment('Liters per kilometer');
            
            // Rate validity period
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            
            // Operating conditions
            $table->enum('work_condition', ['Light', 'Normal', 'Heavy'])->default('Normal');
            $table->text('condition_description')->nullable();
            
            // Rate source
            $table->enum('rate_source', ['Manufacturer', 'Historical Data', 'Field Test', 'Manual'])->default('Manual');
            $table->text('notes')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 100);
            $table->string('updated_by', 100)->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['unit_type_id', 'effective_from', 'is_active']);
            $table->index(['effective_from', 'effective_until']);
            $table->index(['work_condition', 'is_active']);
            
            // Ensure no overlapping periods for same unit type and condition
            $table->unique(['unit_type_id', 'work_condition', 'effective_from'], 'unique_rate_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_consumption_rates');
    }
};