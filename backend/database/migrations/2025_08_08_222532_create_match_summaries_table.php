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
        Schema::create('match_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->unique()->constrained('matches')->onDelete('cascade');

            // Basic stats
            $table->integer('total_kills')->default(0);
            $table->integer('total_deaths')->default(0);
            $table->integer('total_assists')->default(0);
            $table->integer('total_headshots')->default(0);
            $table->integer('total_wallbangs')->default(0);
            $table->integer('total_damage')->default(0);

            // Utility stats
            $table->integer('total_he_damage')->default(0);
            $table->integer('total_effective_flashes')->default(0);
            $table->integer('total_smokes_used')->default(0);
            $table->integer('total_molotovs_used')->default(0);

            // Round stats
            $table->integer('total_first_kills')->default(0);
            $table->integer('total_first_deaths')->default(0);
            $table->integer('total_clutches_1v1_attempted')->default(0);
            $table->integer('total_clutches_1v1_successful')->default(0);
            $table->integer('total_clutches_1v2_attempted')->default(0);
            $table->integer('total_clutches_1v2_successful')->default(0);
            $table->integer('total_clutches_1v3_attempted')->default(0);
            $table->integer('total_clutches_1v3_successful')->default(0);
            $table->integer('total_clutches_1v4_attempted')->default(0);
            $table->integer('total_clutches_1v4_successful')->default(0);
            $table->integer('total_clutches_1v5_attempted')->default(0);
            $table->integer('total_clutches_1v5_successful')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_summaries');
    }
};
