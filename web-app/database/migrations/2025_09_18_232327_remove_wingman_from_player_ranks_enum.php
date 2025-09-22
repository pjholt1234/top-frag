<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite doesn't support MODIFY COLUMN for ENUM, so we need to recreate the table
        Schema::create('player_ranks_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->enum('rank_type', ['faceit', 'premier', 'competitive']); // Removed 'wingman'
            $table->string('rank'); // e.g., "Global Elite", "Level 8"
            $table->integer('rank_value'); // numeric value for sorting
            $table->timestamps();

            // Indexes for performance
            $table->index(['player_id', 'rank_type', 'created_at']);
            $table->index('player_id');
            $table->index('rank_type');
        });

        // Copy data from old table to new table, excluding wingman records
        DB::statement("
            INSERT INTO player_ranks_new (id, player_id, rank_type, rank, rank_value, created_at, updated_at)
            SELECT id, player_id, rank_type, rank, rank_value, created_at, updated_at
            FROM player_ranks
            WHERE rank_type != 'wingman'
        ");

        // Drop the old table and rename the new one
        Schema::dropIfExists('player_ranks');
        Schema::rename('player_ranks_new', 'player_ranks');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the table with wingman included
        Schema::create('player_ranks_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->enum('rank_type', ['faceit', 'premier', 'competitive', 'wingman']); // Restored 'wingman'
            $table->string('rank'); // e.g., "Global Elite", "Level 8"
            $table->integer('rank_value'); // numeric value for sorting
            $table->timestamps();

            // Indexes for performance
            $table->index(['player_id', 'rank_type', 'created_at']);
            $table->index('player_id');
            $table->index('rank_type');
        });

        // Copy all data from current table to new table
        DB::statement('
            INSERT INTO player_ranks_new (id, player_id, rank_type, rank, rank_value, created_at, updated_at)
            SELECT id, player_id, rank_type, rank, rank_value, created_at, updated_at
            FROM player_ranks
        ');

        // Drop the current table and rename the new one
        Schema::dropIfExists('player_ranks');
        Schema::rename('player_ranks_new', 'player_ranks');
    }
};
