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
        Schema::table('player_match_events', function (Blueprint $table) {
            $table->string('rank_type')->nullable()->after('matchmaking_rank');
            $table->integer('rank_value')->nullable()->after('rank_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_match_events', function (Blueprint $table) {
            $table->dropColumn(['rank_type', 'rank_value']);
        });
    }
};
