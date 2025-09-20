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
        Schema::table('users', function (Blueprint $table) {
            $table->string('steam_sharecode')->nullable()->after('steam_link_hash');
            $table->timestamp('steam_sharecode_added_at')->nullable()->after('steam_sharecode');
            $table->boolean('steam_match_processing_enabled')->default(false)->after('steam_sharecode_added_at');

            $table->index('steam_sharecode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['steam_sharecode']);
            $table->dropColumn(['steam_sharecode', 'steam_sharecode_added_at', 'steam_match_processing_enabled']);
        });
    }
};
