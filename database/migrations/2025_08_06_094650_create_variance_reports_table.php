<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ============================================================================
// 3. FIX: variance_reports_table.php - Virtual columns
// ============================================================================

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
            // MYSQL FIX: Remove computed columns, calculate in model
            
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
            
            // MYSQL FIX: Custom shorter index names
            $table->index(['report_date', 'report_type'], 'idx_vr_date_type');
            $table->index(['period_start', 'period_end'], 'idx_vr_period');
            $table->index(['report_status'], 'idx_vr_status');
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