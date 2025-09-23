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
        Schema::table('player_round_events', function (Blueprint $table) {
            // Impact Rating Fields
            $table->decimal('total_impact', 10, 2)->default(0);
            $table->decimal('average_impact', 10, 2)->default(0);
            $table->decimal('round_swing_percent', 5, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_round_events', function (Blueprint $table) {
            $table->dropColumn([
                'total_impact',
                'average_impact',
                'round_swing_percent',
            ]);
        });
    }
};
