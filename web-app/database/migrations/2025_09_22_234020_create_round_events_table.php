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
        Schema::create('round_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->integer('round_number');
            $table->bigInteger('tick_timestamp');
            $table->string('event_type');
            $table->string('winner')->nullable();
            $table->integer('duration')->nullable();

            // Impact Rating Fields
            $table->decimal('total_impact', 12, 2)->default(0);
            $table->integer('total_gunfights')->default(0);
            $table->decimal('average_impact', 10, 2)->default(0);
            $table->decimal('round_swing_percent', 5, 2)->default(0);

            $table->timestamps();

            // Indexes for performance
            $table->index(['match_id', 'round_number']);
            $table->index(['match_id', 'tick_timestamp']);
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('round_events');
    }
};
