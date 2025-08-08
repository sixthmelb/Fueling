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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('unit_code', 20)->unique();
            $table->string('unit_name', 100);
            $table->foreignId('unit_type_id')->constrained('unit_types')->onDelete('restrict');
            $table->decimal('current_hour_meter', 10, 2)->default(0);
            $table->decimal('current_odometer', 10, 2)->default(0);
            $table->string('brand', 50)->nullable();
            $table->string('model', 50)->nullable();
            $table->year('manufacture_year')->nullable();
            $table->decimal('fuel_tank_capacity', 8, 2)->nullable()->comment('Liter');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['unit_type_id', 'is_active']);
            $table->index(['unit_code', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};