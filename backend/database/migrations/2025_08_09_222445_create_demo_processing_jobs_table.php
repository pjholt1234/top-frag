<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\ProcessingStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demo_processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('match_id')->nullable();
            $table->string('processing_status')->default(ProcessingStatus::PENDING->name);
            $table->integer('progress_percentage')->default(0);
            $table->string('current_step')->nullable();
            $table->text('error_message')->nullable();
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->timestamps();

            $table->index('uuid');
            $table->index('match_id');
            $table->index('processing_status');

            $table->foreign('match_id')->references('id')->on('matches')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_processing_jobs');
    }
};
