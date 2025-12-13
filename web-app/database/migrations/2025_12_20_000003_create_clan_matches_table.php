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
        Schema::create('clan_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained('clans')->onDelete('cascade');
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->timestamps();

            // Unique constraint
            $table->unique(['clan_id', 'match_id']);

            // Indexes
            $table->index('clan_id');
            $table->index('match_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clan_matches');
    }
};
