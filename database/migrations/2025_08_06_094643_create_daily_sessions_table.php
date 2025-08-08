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
        Schema::create('daily_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('session_date');
            $table->foreignId('shift_id')->constrained('shifts')->onDelete('restrict');
            $table->string('session_name', 100); // e.g., "2025-01-15 Pagi"
            $table->datetime('start_datetime');
            $table->datetime('end_datetime');
            $table->enum('status', ['Active', 'Closed'])->default('Active');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['session_date', 'shift_id']);
            $table->index(['session_date', 'shift_id', 'status']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_sessions');
    }
};