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
        Schema::create('grenade_favourites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('round_number');
            $table->integer('round_time');
            $table->bigInteger('tick_timestamp');

            $table->string('player_steam_id');
            $table->string('grenade_type', 20);

            $table->float('player_x');
            $table->float('player_y');
            $table->float('player_z');

            $table->float('player_aim_x');
            $table->float('player_aim_y');
            $table->float('player_aim_z');

            // Grenade final position
            $table->float('grenade_final_x')->nullable();
            $table->float('grenade_final_y')->nullable();
            $table->float('grenade_final_z')->nullable();

            // Effects
            $table->integer('damage_dealt')->default(0);
            $table->float('flash_duration')->nullable();
            $table->json('affected_players')->nullable();

            $table->string('throw_type', 20)->default('utility');
            $table->integer('effectiveness_rating')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['match_id', 'round_number']);
            $table->index(['match_id', 'tick_timestamp']);
            $table->index('player_steam_id');
            $table->index('grenade_type');
            $table->index(['round_number', 'round_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grenade_favourites');
    }
};
