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
        Schema::create('daily_outfit_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('slot_name', 50);
            $table->foreignId('wardrobe_item_id')->constrained()->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();

            // Ensure one selection per persona per date per slot
            $table->unique(['persona_id', 'date', 'slot_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_outfit_selections');
    }
};
