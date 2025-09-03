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
        Schema::table('gunfight_events', function (Blueprint $table) {
            $table->string('flash_assister_steam_id')->nullable()->after('victor_steam_id');
            $table->index('flash_assister_steam_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gunfight_events', function (Blueprint $table) {
            $table->dropIndex(['flash_assister_steam_id']);
            $table->dropColumn('flash_assister_steam_id');
        });
    }
};
