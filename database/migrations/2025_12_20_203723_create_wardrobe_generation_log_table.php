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
        Schema::create('wardrobe_generation_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained()->onDelete('cascade');
            $table->string('slot_name', 50);
            $table->json('tags_used')->nullable()->comment('Tags selected during generation');
            $table->integer('outfits_generated')->comment('Number of outfits created');
            $table->timestamp('generated_at')->useCurrent();

            $table->index(['persona_id', 'generated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wardrobe_generation_log');
    }
};
