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
        Schema::create('physical_stock_checks', function (Blueprint $table) {
            $table->id();
            $table->string('check_number', 50)->unique();
            
            // Polymorphic relation for checkable (storage or truck)
            $table->morphs('checkable'); // creates checkable_type & checkable_id
            
            $table->date('check_date');
            $table->time('check_time');
            $table->datetime('check_datetime')->storedAs("CONCAT(check_date, ' ', check_time)");
            
            // Stock levels
            $table->decimal('system_level', 10, 2)->comment('Level according to system');
            $table->decimal('physical_level', 10, 2)->comment('Actual measured level');
            $table->decimal('variance', 10, 2)->storedAs('physical_level - system_level');
            $table->decimal('variance_percentage', 8, 4)->storedAs('(variance / system_level) * 100');
            
            // Check details
            $table->string('checker_name', 100);
            $table->enum('check_method', ['Dipstick', 'Gauge', 'Flow Meter', 'Visual'])->default('Dipstick');
            $table->enum('variance_status', ['Normal', 'Warning', 'Critical'])->default('Normal');
            
            // Action taken
            $table->text('notes')->nullable();
            $table->text('corrective_action')->nullable();
            $table->boolean('system_adjusted')->default(false);
            $table->decimal('adjustment_amount', 10, 2)->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['checkable_type', 'checkable_id', 'check_date']);
            $table->index(['check_date', 'variance_status']);
            $table->index(['variance_status']);
            $table->index(['system_adjusted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('physical_stock_checks');
    }
};