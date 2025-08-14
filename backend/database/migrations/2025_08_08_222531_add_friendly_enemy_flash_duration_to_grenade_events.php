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
            $table->float('friendly_flash_duration')->nullable()->after('flash_duration'); // Total flash duration on teammates
            $table->float('enemy_flash_duration')->nullable()->after('friendly_flash_duration'); // Total flash duration on enemies
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grenade_events', function (Blueprint $table) {
            $table->dropColumn(['friendly_flash_duration', 'enemy_flash_duration']);
        });
    }
};
