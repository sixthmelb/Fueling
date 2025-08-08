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
        Schema::create('fuel_trucks', function (Blueprint $table) {
            $table->id();
            $table->string('truck_code', 20)->unique();
            $table->string('truck_name', 100);
            $table->decimal('capacity', 8, 2)->comment('Tank capacity in liters');
            $table->decimal('current_level', 8, 2)->default(0)->comment('Current fuel level in liters');
            $table->string('license_plate', 20)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('brand', 50)->nullable();
            $table->string('model', 50)->nullable();
            $table->year('manufacture_year')->nullable();
            $table->enum('fuel_type', ['Solar', 'Bensin', 'Pertamax'])->default('Solar');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['truck_code', 'is_active']);
            $table->index(['fuel_type', 'is_active']);
            $table->index(['driver_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_trucks');
    }
};