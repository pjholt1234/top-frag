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
            $table->integer('fire_grenades_thrown')->default(0)->after('flashes_thrown');
            $table->integer('smokes_thrown')->default(0)->after('fire_grenades_thrown');
            $table->integer('hes_thrown')->default(0)->after('smokes_thrown');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_round_events', function (Blueprint $table) {
            $table->dropColumn(['fire_grenades_thrown', 'smokes_thrown', 'hes_thrown']);
        });
    }
};
