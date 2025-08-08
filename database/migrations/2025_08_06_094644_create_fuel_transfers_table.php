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
        Schema::create('fuel_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 50)->unique();
            $table->foreignId('fuel_storage_id')->constrained('fuel_storages')->onDelete('restrict');
            $table->foreignId('fuel_truck_id')->constrained('fuel_trucks')->onDelete('restrict');
            $table->foreignId('daily_session_id')->constrained('daily_sessions')->onDelete('restrict');
            $table->decimal('transferred_amount', 10, 2)->comment('Amount transferred in liters');
            $table->decimal('storage_level_before', 10, 2)->comment('Storage level before transfer');
            $table->decimal('storage_level_after', 10, 2)->comment('Storage level after transfer');
            $table->decimal('truck_level_before', 8, 2)->comment('Truck level before transfer');
            $table->decimal('truck_level_after', 8, 2)->comment('Truck level after transfer');
            $table->datetime('transfer_datetime');
            $table->string('operator_name', 100);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // MYSQL FIX: Custom shorter index names
            $table->index(['fuel_storage_id', 'transfer_datetime'], 'idx_ft_storage_datetime');
            $table->index(['fuel_truck_id', 'transfer_datetime'], 'idx_ft_truck_datetime');
            $table->index(['daily_session_id'], 'idx_ft_session');
            $table->index(['transfer_datetime'], 'idx_ft_datetime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_transfers');
    }
};