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
        Schema::create('clans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owned_by')->constrained('users')->onDelete('cascade');
            $table->uuid('invite_link')->unique();
            $table->string('name');
            $table->string('tag')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('invite_link');
            $table->index('owned_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clans');
    }
};
