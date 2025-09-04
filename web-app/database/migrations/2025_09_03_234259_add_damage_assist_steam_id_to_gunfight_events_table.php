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
            $table->string('damage_assist_steam_id')->nullable()->after('flash_assister_steam_id');
            $table->index('damage_assist_steam_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gunfight_events', function (Blueprint $table) {
            $table->dropIndex(['damage_assist_steam_id']);
            $table->dropColumn('damage_assist_steam_id');
        });
    }
};
