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
        Schema::create('variance_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_number', 50)->unique();
            $table->date('report_date');
            $table->enum('report_type', ['Daily', 'Weekly', 'Monthly'])->default('Daily');
            $table->date('period_start');
            $table->date('period_end');
            
            // Overall variance summary
            $table->decimal('total_system_fuel', 12, 2)->comment('Total fuel according to system');
            $table->decimal('total_physical_fuel', 12, 2)->comment('Total fuel from physical checks');
            $table->decimal('total_variance', 12, 2)->storedAs('total_physical_fuel - total_system_fuel');
            $table->decimal('total_variance_percentage', 8, 4)->storedAs('(total_variance / total_system_fuel) * 100');
            
            // Breakdown by source type
            $table->decimal('storage_variance', 10, 2)->default(0);
            $table->decimal('truck_variance', 10, 2)->default(0);
            $table->integer('total_checks_performed')->default(0);
            $table->integer('critical_variances_count')->default(0);
            
            // Status and actions
            $table->enum('report_status', ['Draft', 'Final', 'Approved'])->default('Draft');
            $table->text('summary_notes')->nullable();
            $table->text('recommended_actions')->nullable();
            
            // Approval workflow
            $table->string('prepared_by', 100);
            $table->string('reviewed_by', 100)->nullable();
            $table->string('approved_by', 100)->nullable();
            $table->datetime('approved_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['report_date', 'report_type']);
            $table->index(['period_start', 'period_end']);
            $table->index(['report_status']);
            $table->index(['total_variance_percentage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variance_reports');
    }
};