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
        Schema::create('player_match_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');

            // Basic stats
            $table->integer('kills')->default(0);
            $table->integer('deaths')->default(0);
            $table->integer('assists')->default(0);
            $table->integer('headshots')->default(0);
            $table->integer('wallbangs')->default(0);
            $table->integer('first_kills')->default(0);
            $table->integer('first_deaths')->default(0);

            // Damage stats
            $table->integer('total_damage')->default(0);
            $table->float('average_damage_per_round')->default(0);
            $table->integer('damage_taken')->default(0);

            // Utility stats
            $table->integer('he_damage')->default(0);
            $table->integer('effective_flashes')->default(0);
            $table->integer('smokes_used')->default(0);
            $table->integer('molotovs_used')->default(0);
            $table->integer('flashbangs_used')->default(0);

            // Clutch stats
            $table->integer('clutches_1v1_attempted')->default(0);
            $table->integer('clutches_1v1_successful')->default(0);
            $table->integer('clutches_1v2_attempted')->default(0);
            $table->integer('clutches_1v2_successful')->default(0);
            $table->integer('clutches_1v3_attempted')->default(0);
            $table->integer('clutches_1v3_successful')->default(0);
            $table->integer('clutches_1v4_attempted')->default(0);
            $table->integer('clutches_1v4_successful')->default(0);
            $table->integer('clutches_1v5_attempted')->default(0);
            $table->integer('clutches_1v5_successful')->default(0);

            // Calculated stats
            $table->float('kd_ratio')->default(0);
            $table->float('headshot_percentage')->default(0);
            $table->float('clutch_success_rate')->default(0);

            $table->timestamps();

            // Unique constraint
            $table->unique(['match_id', 'player_id']);

            // Indexes
            $table->index('match_id');
            $table->index('player_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_match_summaries');
    }
};
