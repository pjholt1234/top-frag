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
        Schema::table('grenade_events', function (Blueprint $table) {
            $table->integer('team_damage_dealt')->default(0)->after('damage_dealt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grenade_events', function (Blueprint $table) {
            $table->dropColumn('team_damage_dealt');
        });
    }
};
