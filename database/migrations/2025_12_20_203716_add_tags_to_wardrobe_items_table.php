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
        Schema::table('wardrobe_items', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('accessories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wardrobe_items', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};
