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
        Schema::create('event_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained()->onDelete('cascade');
            $table->dateTime('scheduled_at');
            // Types: text message, generate image, or lifecycle event
            $table->enum('type', ['text', 'image_generation', 'wake_up', 'sleep']);
            $table->text('context_prompt')->nullable(); // Instruction for Gemini
            // Status tracking to prevent duplicate sending
            $table->enum('status', ['pending', 'sent', 'rescheduled', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_schedules');
    }
};
