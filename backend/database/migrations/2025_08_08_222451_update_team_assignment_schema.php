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
        // Update matches table - add winning_team field
        Schema::table('matches', function (Blueprint $table) {
            $table->string('winning_team', 10)->default('A')->after('losing_team_score');
            $table->index('winning_team');
        });

        // Update match_players table - remove side_start column
        Schema::table('match_players', function (Blueprint $table) {
            $table->dropColumn('side_start');
        });

        // Update team column to store 'A' or 'B' instead of 'CT' or 'T'
        // Since we're not maintaining backward compatibility, we can modify the column
        Schema::table('match_players', function (Blueprint $table) {
            $table->string('team', 10)->change(); // Now stores 'A' or 'B'
        });

        // Update the index name to be more descriptive
        Schema::table('match_players', function (Blueprint $table) {
            $table->dropIndex(['team']);
            $table->index('team', 'idx_match_players_team');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert match_players table changes
        Schema::table('match_players', function (Blueprint $table) {
            $table->dropIndex('idx_match_players_team');
            $table->index('team');
            $table->string('team', 10)->change(); // Revert to original
            $table->string('side_start', 10)->nullable();
        });

        // Revert matches table changes
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex(['winning_team']);
            $table->dropColumn('winning_team');
        });
    }
};
