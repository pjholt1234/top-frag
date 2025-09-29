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
        Schema::table('player_round_events', function (Blueprint $table) {
            $table->integer('smoke_blocking_duration')->default(0)->after('grenade_effectiveness');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_round_events', function (Blueprint $table) {
            $table->dropColumn('smoke_blocking_duration');
        });
    }
};
