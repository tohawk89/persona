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
        Schema::create('wardrobe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained()->onDelete('cascade');
            $table->enum('slot_name', ['casual_daytime', 'casual_nighttime', 'formal', 'workout', 'sleepwear', 'custom']);
            $table->text('description')->comment('Full outfit description');
            $table->string('upper_body')->nullable()->comment('Top/dress only');
            $table->string('lower_body')->nullable()->comment('Pants/skirt (null for dresses)');
            $table->string('footwear')->nullable()->comment('Shoes/sandals');
            $table->text('accessories')->nullable()->comment('Jewelry, bags, etc.');
            $table->boolean('is_primary')->default(false)->comment('The main outfit for this slot');
            $table->timestamp('last_worn_at')->nullable()->comment('Last time this outfit was selected');
            $table->integer('wear_count')->default(0)->comment('Total times worn');
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['persona_id', 'slot_name']);
            $table->index(['persona_id', 'slot_name', 'last_worn_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wardrobe_items');
    }
};
