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
            // MYSQL FIX: Remove computed datetime column for now
            
            // Stock levels
            $table->decimal('system_level', 10, 2)->comment('Level according to system');
            $table->decimal('physical_level', 10, 2)->comment('Actual measured level');
            // MYSQL FIX: Remove computed columns, calculate in model
            
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
            
            // MYSQL FIX: Custom shorter index names
            $table->index(['checkable_type', 'checkable_id', 'check_date'], 'idx_psc_checkable_date');
            $table->index(['check_date', 'variance_status'], 'idx_psc_date_status');
            $table->index(['variance_status'], 'idx_psc_status');
            $table->index(['system_adjusted'], 'idx_psc_adjusted');
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