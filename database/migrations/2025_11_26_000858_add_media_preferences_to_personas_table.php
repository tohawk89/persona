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
        Schema::table('personas', function (Blueprint $table) {
            $table->enum('voice_frequency', ['never', 'rare', 'moderate', 'frequent'])->default('moderate')->after('sleep_time');
            $table->enum('image_frequency', ['never', 'rare', 'moderate', 'frequent'])->default('moderate')->after('voice_frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn(['voice_frequency', 'image_frequency']);
        });
    }
};
