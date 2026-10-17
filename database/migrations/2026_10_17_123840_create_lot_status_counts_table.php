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
        Schema::create('lot_status_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_lot_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->unsignedSmallInteger('count');

            $table->unique(['collection_lot_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lot_status_counts');
    }
};
