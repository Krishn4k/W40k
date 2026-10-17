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
        Schema::create('enhancement_point_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enhancement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_set_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('points');

            $table->unique(['enhancement_id', 'point_set_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enhancement_point_costs');
    }
};
