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
        // Add impact_percentage to player_round_events
        Schema::table('player_round_events', function (Blueprint $table) {
            $table->decimal('impact_percentage', 8, 2)->default(0)->after('round_swing_percent');
        });

        // Add impact_percentage to player_match_events
        Schema::table('player_match_events', function (Blueprint $table) {
            $table->decimal('impact_percentage', 8, 2)->default(0)->after('match_swing_percent');
        });

        // Add impact_percentage to round_events
        Schema::table('round_events', function (Blueprint $table) {
            $table->decimal('impact_percentage', 8, 2)->default(0)->after('round_swing_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_round_events', function (Blueprint $table) {
            $table->dropColumn('impact_percentage');
        });

        Schema::table('player_match_events', function (Blueprint $table) {
            $table->dropColumn('impact_percentage');
        });

        Schema::table('round_events', function (Blueprint $table) {
            $table->dropColumn('impact_percentage');
        });
    }
};
