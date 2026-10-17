<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reference tables fed by the BSData import, matched on the BSData identifier to update instead of duplicating.
     *
     * @var list<string>
     */
    private array $tables = ['factions', 'units', 'keywords', 'detachments', 'enhancements'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('bsdata_id', 64)->nullable()->unique()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique(['bsdata_id']);
                $table->dropColumn('bsdata_id');
            });
        }
    }
};
