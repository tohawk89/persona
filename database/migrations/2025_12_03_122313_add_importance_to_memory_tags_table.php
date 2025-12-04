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
        Schema::table('memory_tags', function (Blueprint $table) {
            $table->integer('importance')->default(5)->after('context');
            $table->timestamp('last_consolidated_at')->nullable()->after('importance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memory_tags', function (Blueprint $table) {
            $table->dropColumn(['importance', 'last_consolidated_at']);
        });
    }
};
