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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            // Optional: If you want to support multiple users later, link to user_id
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('persona_id')->constrained()->onDelete('cascade');

            $table->enum('sender_type', ['user', 'bot']);
            $table->text('content')->nullable(); // Nullable if it's just an image
            $table->string('image_path')->nullable(); // For sent/received photos

            // Distinction between a direct reply and a scheduled event
            $table->boolean('is_event_trigger')->default(false);

            $table->timestamps();

            // Index for faster history retrieval
            $table->index(['created_at', 'persona_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
