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
        Schema::create('damage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->integer('armor_damage')->default(0);
            $table->string('attacker_steam_id');
            $table->integer('damage')->default(0);
            $table->boolean('headshot')->default(false);
            $table->integer('health_damage')->default(0);
            $table->integer('round_number');
            $table->integer('round_time'); // Seconds into the round
            $table->bigInteger('tick_timestamp');
            $table->string('victim_steam_id');
            $table->string('weapon', 50);

            $table->timestamps();

            // Indexes
            $table->index(['match_id', 'round_number']);
            $table->index(['match_id', 'tick_timestamp']);
            $table->index(['attacker_steam_id', 'victim_steam_id']);
            $table->index('attacker_steam_id');
            $table->index('victim_steam_id');
            $table->index(['round_number', 'round_time']);
            $table->index('weapon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damage_events');
    }
};
