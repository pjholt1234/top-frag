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
            $table->string('steam_persona_name')->nullable();
            $table->string('steam_profile_url')->nullable();
            $table->string('steam_avatar')->nullable();
            $table->string('steam_avatar_medium')->nullable();
            $table->string('steam_avatar_full')->nullable();
            $table->integer('steam_persona_state')->nullable();
            $table->integer('steam_community_visibility_state')->nullable();
            $table->timestamp('steam_profile_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'steam_persona_name',
                'steam_profile_url',
                'steam_avatar',
                'steam_avatar_medium',
                'steam_avatar_full',
                'steam_persona_state',
                'steam_community_visibility_state',
                'steam_profile_updated_at',
            ]);
        });
    }
};
