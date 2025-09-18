<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove wingman from the enum constraint
        DB::statement("ALTER TABLE player_ranks MODIFY COLUMN rank_type ENUM('faceit', 'premier', 'competitive') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore wingman to the enum constraint
        DB::statement("ALTER TABLE player_ranks MODIFY COLUMN rank_type ENUM('faceit', 'premier', 'competitive', 'wingman') NOT NULL");
    }
};
