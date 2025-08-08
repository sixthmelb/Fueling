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
        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('type_code', 10)->unique();
            $table->string('type_name', 100);
            $table->text('description')->nullable();
            $table->decimal('default_consumption_per_hour', 8, 2)->nullable()->comment('Default L/hour');
            $table->decimal('default_consumption_per_km', 8, 2)->nullable()->comment('Default L/km');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['type_code', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_types');
    }
};