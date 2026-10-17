<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * French names still to be checked: one row per reference record, removed once the name is validated.
     */
    public function up(): void
    {
        Schema::create('translation_reviews', function (Blueprint $table) {
            $table->id();
            $table->morphs('translatable');
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translation_reviews');
    }
};
