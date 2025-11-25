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
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Sarah"
            $table->string('avatar_ref_path')->nullable(); // Path to reference image
            $table->text('system_prompt'); // "You are a sarcastic assistant..."
            $table->time('wake_time')->default('08:00');
            $table->time('sleep_time')->default('23:00');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
