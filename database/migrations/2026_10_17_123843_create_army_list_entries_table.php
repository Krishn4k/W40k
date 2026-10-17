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
        Schema::create('army_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('army_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('model_count');
            $table->foreignId('collection_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('enhancement_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_warlord')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['army_list_id', 'collection_lot_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('army_list_entries');
    }
};
