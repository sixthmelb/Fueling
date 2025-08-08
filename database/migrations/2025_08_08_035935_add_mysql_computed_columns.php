<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ============================================================================
// 6. ADD: MySQL specific migration to handle computed columns
// ============================================================================

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            
            // 1. TAMBAHKAN KOLOM FISIK DULU
            Schema::table('fuel_transactions', function (Blueprint $table) {
                $table->decimal('hour_meter_diff', 10, 2)->nullable()->after('current_hour_meter');
                $table->decimal('odometer_diff', 10, 2)->nullable()->after('current_odometer');
            });
            
            Schema::table('physical_stock_checks', function (Blueprint $table) {
                $table->decimal('variance', 10, 2)->nullable()->after('physical_level');
                $table->decimal('variance_percentage', 8, 4)->nullable()->after('variance');
                $table->datetime('check_datetime')->nullable()->after('check_time');
            });
            
            Schema::table('variance_reports', function (Blueprint $table) {
                $table->decimal('total_variance', 12, 2)->nullable()->after('total_physical_fuel');
                $table->decimal('total_variance_percentage', 8, 4)->nullable()->after('total_variance');
            });
            
            // 2. BUAT TRIGGER SETELAH KOLOM ADA
            
            // Trigger untuk fuel_transactions
            DB::unprepared('
                CREATE TRIGGER fuel_transactions_compute_insert 
                BEFORE INSERT ON fuel_transactions
                FOR EACH ROW
                SET NEW.hour_meter_diff = NEW.current_hour_meter - NEW.previous_hour_meter,
                    NEW.odometer_diff = NEW.current_odometer - NEW.previous_odometer;
            ');
            
            DB::unprepared('
                CREATE TRIGGER fuel_transactions_compute_update 
                BEFORE UPDATE ON fuel_transactions
                FOR EACH ROW
                SET NEW.hour_meter_diff = NEW.current_hour_meter - NEW.previous_hour_meter,
                    NEW.odometer_diff = NEW.current_odometer - NEW.previous_odometer;
            ');
            
            // Trigger untuk physical_stock_checks
            DB::unprepared('
                CREATE TRIGGER physical_stock_checks_compute_insert 
                BEFORE INSERT ON physical_stock_checks
                FOR EACH ROW
                SET NEW.variance = NEW.physical_level - NEW.system_level,
                    NEW.variance_percentage = CASE 
                        WHEN NEW.system_level = 0 THEN 0 
                        ELSE ((NEW.physical_level - NEW.system_level) / NEW.system_level) * 100 
                    END,
                    NEW.check_datetime = CONCAT(NEW.check_date, " ", NEW.check_time);
            ');
            
            DB::unprepared('
                CREATE TRIGGER physical_stock_checks_compute_update 
                BEFORE UPDATE ON physical_stock_checks
                FOR EACH ROW
                SET NEW.variance = NEW.physical_level - NEW.system_level,
                    NEW.variance_percentage = CASE 
                        WHEN NEW.system_level = 0 THEN 0 
                        ELSE ((NEW.physical_level - NEW.system_level) / NEW.system_level) * 100 
                    END,
                    NEW.check_datetime = CONCAT(NEW.check_date, " ", NEW.check_time);
            ');
            
            // Trigger untuk variance_reports
            DB::unprepared('
                CREATE TRIGGER variance_reports_compute_insert 
                BEFORE INSERT ON variance_reports
                FOR EACH ROW
                SET NEW.total_variance = NEW.total_physical_fuel - NEW.total_system_fuel,
                    NEW.total_variance_percentage = CASE 
                        WHEN NEW.total_system_fuel = 0 THEN 0 
                        ELSE ((NEW.total_physical_fuel - NEW.total_system_fuel) / NEW.total_system_fuel) * 100 
                    END;
            ');
            
            DB::unprepared('
                CREATE TRIGGER variance_reports_compute_update 
                BEFORE UPDATE ON variance_reports
                FOR EACH ROW
                SET NEW.total_variance = NEW.total_physical_fuel - NEW.total_system_fuel,
                    NEW.total_variance_percentage = CASE 
                        WHEN NEW.total_system_fuel = 0 THEN 0 
                        ELSE ((NEW.total_physical_fuel - NEW.total_system_fuel) / NEW.total_system_fuel) * 100 
                    END;
            ');
            
            // 3. UPDATE EXISTING RECORDS (jika ada)
            DB::statement('
                UPDATE fuel_transactions 
                SET hour_meter_diff = current_hour_meter - previous_hour_meter,
                    odometer_diff = current_odometer - previous_odometer
            ');
            
            DB::statement('
                UPDATE physical_stock_checks 
                SET variance = physical_level - system_level,
                    variance_percentage = CASE 
                        WHEN system_level = 0 THEN 0 
                        ELSE ((physical_level - system_level) / system_level) * 100 
                    END,
                    check_datetime = CONCAT(check_date, " ", check_time)
            ');
            
            DB::statement('
                UPDATE variance_reports 
                SET total_variance = total_physical_fuel - total_system_fuel,
                    total_variance_percentage = CASE 
                        WHEN total_system_fuel = 0 THEN 0 
                        ELSE ((total_physical_fuel - total_system_fuel) / total_system_fuel) * 100 
                    END
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Drop triggers first
            DB::unprepared('DROP TRIGGER IF EXISTS fuel_transactions_compute_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS fuel_transactions_compute_update');
            DB::unprepared('DROP TRIGGER IF EXISTS physical_stock_checks_compute_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS physical_stock_checks_compute_update');
            DB::unprepared('DROP TRIGGER IF EXISTS variance_reports_compute_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS variance_reports_compute_update');
            
            // Drop columns
            Schema::table('fuel_transactions', function (Blueprint $table) {
                $table->dropColumn(['hour_meter_diff', 'odometer_diff']);
            });
            
            Schema::table('physical_stock_checks', function (Blueprint $table) {
                $table->dropColumn(['variance', 'variance_percentage', 'check_datetime']);
            });
            
            Schema::table('variance_reports', function (Blueprint $table) {
                $table->dropColumn(['total_variance', 'total_variance_percentage']);
            });
        }
    }
};