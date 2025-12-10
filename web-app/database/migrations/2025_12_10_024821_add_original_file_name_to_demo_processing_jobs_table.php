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
            $table->string('original_file_name')->nullable()->after('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demo_processing_jobs', function (Blueprint $table) {
            $table->dropColumn('original_file_name');
        });
    }
};
