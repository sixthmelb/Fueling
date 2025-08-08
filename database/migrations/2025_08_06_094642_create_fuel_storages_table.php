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
        Schema::create('fuel_storages', function (Blueprint $table) {
            $table->id();
            $table->string('storage_code', 20)->unique();
            $table->string('storage_name', 100);
            $table->decimal('capacity', 10, 2)->comment('Total capacity in liters');
            $table->decimal('current_level', 10, 2)->default(0)->comment('Current fuel level in liters');
            $table->decimal('minimum_level', 10, 2)->default(0)->comment('Alert threshold in liters');
            $table->string('location', 255)->nullable();
            $table->enum('fuel_type', ['Solar', 'Bensin', 'Pertamax'])->default('Solar');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['storage_code', 'is_active']);
            $table->index(['fuel_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_storages');
    }
};