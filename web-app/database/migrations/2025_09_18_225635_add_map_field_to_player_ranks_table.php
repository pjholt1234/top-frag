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
        Schema::table('player_ranks', function (Blueprint $table) {
            $table->string('map')->nullable()->after('rank_type');

            // Update the composite index to include map
            $table->dropIndex(['player_id', 'rank_type', 'created_at']);
            $table->index(['player_id', 'rank_type', 'map', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_ranks', function (Blueprint $table) {
            // Restore the original index
            $table->dropIndex(['player_id', 'rank_type', 'map', 'created_at']);
            $table->index(['player_id', 'rank_type', 'created_at']);

            $table->dropColumn('map');
        });
    }
};
