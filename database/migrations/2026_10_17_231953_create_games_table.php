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
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('played_on');
            $table->foreignId('army_list_id')->nullable()->constrained()->nullOnDelete();
            // Copy of the list as played: kept even if the list changes or is deleted afterwards.
            $table->string('list_name')->nullable();
            $table->foreignId('faction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('detachment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('list_points')->nullable();
            $table->text('list_snapshot')->nullable();
            $table->string('opponent_name')->nullable();
            $table->foreignId('opponent_faction_id')->nullable()->constrained('factions')->nullOnDelete();
            $table->string('mission')->nullable();
            $table->string('result', 8);
            $table->unsignedSmallInteger('score_for')->nullable();
            $table->unsignedSmallInteger('score_against')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'played_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
