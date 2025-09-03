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
            $table->boolean('flash_leads_to_kill')->default(false)->after('enemy_players_affected');
            $table->boolean('flash_leads_to_death')->default(false)->after('flash_leads_to_kill');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grenade_events', function (Blueprint $table) {
            $table->dropColumn(['flash_leads_to_kill', 'flash_leads_to_death']);
        });
    }
};
