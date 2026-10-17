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
        Schema::create('unit_point_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_set_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('model_count');
            $table->unsignedSmallInteger('points');

            $table->unique(['unit_id', 'point_set_id', 'model_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_point_costs');
    }
};
