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
        Schema::create('player_ranks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->enum('rank_type', ['faceit', 'premier', 'competitive', 'wingman']);
            $table->string('rank'); // e.g., "Global Elite", "Level 8"
            $table->integer('rank_value'); // numeric value for sorting
            $table->timestamps();

            // Indexes for performance
            $table->index(['player_id', 'rank_type', 'created_at']);
            $table->index('player_id');
            $table->index('rank_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_ranks');
    }
};
