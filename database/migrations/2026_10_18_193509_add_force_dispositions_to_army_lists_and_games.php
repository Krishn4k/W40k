<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Force disposition picked for a list (Warhammer Event), and both players' dispositions in a game.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('army_lists', function (Blueprint $table) {
            $table->string('force_disposition', 30)->nullable()->after('game_format_id');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->string('force_disposition', 30)->nullable()->after('list_snapshot');
            $table->string('opponent_force_disposition', 30)->nullable()->after('opponent_faction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['force_disposition', 'opponent_force_disposition']);
        });

        Schema::table('army_lists', function (Blueprint $table) {
            $table->dropColumn('force_disposition');
        });
    }
};
