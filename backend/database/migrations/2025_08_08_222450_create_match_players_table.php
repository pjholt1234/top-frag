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
        Schema::create('match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->string('team', 10); // 'CT' or 'T'
            $table->string('side_start', 10); // Which side they started on ('CT' or 'T')
            $table->timestamps();

            // Unique constraint
            $table->unique(['match_id', 'player_id']);

            // Indexes
            $table->index('match_id');
            $table->index('player_id');
            $table->index('team');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_players');
    }
};
