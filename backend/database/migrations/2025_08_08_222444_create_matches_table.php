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
            $table->string('match_hash', 64)->unique();
            $table->string('map', 50);
            $table->integer('winning_team_score');
            $table->integer('losing_team_score');
            $table->string('match_type');
            $table->timestamp('start_timestamp')->nullable();
            $table->timestamp('end_timestamp')->nullable();
            $table->integer('total_rounds');
            $table->integer('total_fight_events')->default(0);
            $table->integer('total_grenade_events')->default(0);
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
