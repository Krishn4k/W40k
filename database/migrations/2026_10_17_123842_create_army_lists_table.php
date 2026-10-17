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
        Schema::create('army_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('faction_id')->constrained()->restrictOnDelete();
            $table->foreignId('detachment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('game_format_id')->constrained()->restrictOnDelete();
            $table->foreignId('point_set_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('army_lists');
    }
};
