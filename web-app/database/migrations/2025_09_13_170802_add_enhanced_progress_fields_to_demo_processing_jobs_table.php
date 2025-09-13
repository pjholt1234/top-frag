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
        Schema::table('demo_processing_jobs', function (Blueprint $table) {
            $table->integer('step_progress')->default(0)->after('progress_percentage');
            $table->integer('total_steps')->default(18)->after('step_progress');
            $table->integer('current_step_num')->default(1)->after('total_steps');
            $table->datetime('start_time')->nullable()->after('started_at');
            $table->datetime('last_update_time')->nullable()->after('start_time');
            $table->string('error_code')->nullable()->after('error_message');
            $table->json('context')->nullable()->after('error_code');
            $table->boolean('is_final')->default(false)->after('context');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demo_processing_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'step_progress',
                'total_steps',
                'current_step_num',
                'start_time',
                'last_update_time',
                'error_code',
                'context',
                'is_final',
            ]);
        });
    }
};
