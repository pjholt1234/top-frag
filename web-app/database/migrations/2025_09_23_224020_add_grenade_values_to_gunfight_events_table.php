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
            $table->integer('player_1_grenade_value')->default(0)->after('player_2_equipment_value');
            $table->integer('player_2_grenade_value')->default(0)->after('player_1_grenade_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gunfight_events', function (Blueprint $table) {
            $table->dropColumn(['player_1_grenade_value', 'player_2_grenade_value']);
        });
    }
};
