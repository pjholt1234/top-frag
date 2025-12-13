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
        Schema::create('clan_leaderboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained('clans')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('leaderboard_type', ['aim', 'impact', 'round_swing', 'fragger', 'support', 'opener', 'closer']);
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('position');
            $table->decimal('value', 10, 2);
            $table->timestamps();

            // Indexes
            $table->index(['clan_id', 'leaderboard_type', 'start_date', 'end_date'], 'clan_lb_clan_type_dates_idx');
            $table->index(['clan_id', 'user_id', 'leaderboard_type', 'start_date', 'end_date'], 'clan_lb_clan_user_type_dates_idx');
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clan_leaderboards');
    }
};
