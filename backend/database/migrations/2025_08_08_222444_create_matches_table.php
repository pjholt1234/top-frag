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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->string('match_hash', 64)->unique()->nullable();
            $table->string('map', 50)->nullable();
            $table->integer('winning_team_score')->nullable();
            $table->integer('losing_team_score')->nullable();
            $table->string('match_type')->nullable();
            $table->timestamp('start_timestamp')->nullable();
            $table->timestamp('end_timestamp')->nullable();
            $table->integer('total_rounds')->nullable();
            $table->integer('total_fight_events')->default(0);
            $table->integer('total_grenade_events')->default(0);
            $table->integer('playback_ticks')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('match_hash');
            $table->index('map');
            $table->index('match_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
