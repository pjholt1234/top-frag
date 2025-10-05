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
        Schema::table('player_match_aim_weapon_events', function (Blueprint $table) {
            $table->dropColumn('average_time_to_damage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_match_aim_weapon_events', function (Blueprint $table) {
            $table->decimal('average_time_to_damage', 8, 4)->default(0);
        });
    }
};
