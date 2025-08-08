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
        Schema::create('gunfight_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->integer('round_number');
            $table->integer('round_time'); // Seconds into the round
            $table->bigInteger('tick_timestamp');

            $table->foreignId('player_1_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('player_2_id')->constrained('players')->onDelete('cascade');

            $table->integer('player_1_hp_start');
            $table->integer('player_2_hp_start');
            $table->integer('player_1_armor')->default(0);
            $table->integer('player_2_armor')->default(0);
            $table->boolean('player_1_flashed')->default(false);
            $table->boolean('player_2_flashed')->default(false);
            $table->string('player_1_weapon', 50);
            $table->string('player_2_weapon', 50);
            $table->integer('player_1_equipment_value')->default(0);
            $table->integer('player_2_equipment_value')->default(0);

            $table->float('player_1_x');
            $table->float('player_1_y');
            $table->float('player_1_z');
            $table->float('player_2_x');
            $table->float('player_2_y');
            $table->float('player_2_z');

            $table->float('distance'); // Distance between players
            $table->boolean('headshot')->default(false);
            $table->boolean('wallbang')->default(false);
            $table->integer('penetrated_objects')->default(0);

            $table->foreignId('victor_id')->nullable()->constrained('players')->onDelete('set null'); // NULL if no clear winner
            $table->integer('damage_dealt')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['match_id', 'round_number']);
            $table->index(['match_id', 'tick_timestamp']);
            $table->index(['player_1_id', 'player_2_id']);
            $table->index('victor_id');
            $table->index(['round_number', 'round_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gunfight_events');
    }
};
