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
        Schema::create('memory_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained()->onDelete('cascade');
            $table->enum('target', ['user', 'self']); // Is this fact about You or the Bot?
            $table->string('category'); // e.g. "music", "food", "work"
            $table->text('value'); // e.g. "likes linkin park"
            $table->text('context')->nullable(); // Source: "Chat on 24th Nov"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memory_tags');
    }
};
