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
            $table->enum('player_1_side', ['CT', 'T'])->after('player_1_steam_id')->nullable();
            $table->enum('player_2_side', ['CT', 'T'])->after('player_2_steam_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gunfight_events', function (Blueprint $table) {
            $table->dropColumn(['player_1_side', 'player_2_side']);
        });
    }
};
