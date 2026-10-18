<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Warhammer 40,000 11th edition army building: several detachments per list (Detachment Points and
 * "Unique" tags), points by copy rank and wargear costs, "Upgrade" enhancements, Leader and Support units.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('detachments', function (Blueprint $table) {
            $table->unsignedTinyInteger('detachment_points')->nullable()->after('name');
            $table->string('unique_tag', 100)->nullable()->after('detachment_points');
            $table->json('force_dispositions')->nullable()->after('unique_tag');
        });

        Schema::table('enhancements', function (Blueprint $table) {
            $table->boolean('is_upgrade')->default(false)->after('name');
        });

        // Points by squad size and by copy rank: "from_copy" = 3 is the cost of the 3rd and following copies.
        Schema::table('unit_point_costs', function (Blueprint $table) {
            $table->unsignedTinyInteger('from_copy')->default(1)->after('model_count');
            $table->unique(['unit_id', 'point_set_id', 'model_count', 'from_copy'], 'unit_point_costs_unique_tier');
        });
        Schema::table('unit_point_costs', function (Blueprint $table) {
            $table->dropUnique(['unit_id', 'point_set_id', 'model_count']);
        });

        Schema::create('unit_wargear_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_set_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('points');

            $table->unique(['unit_id', 'point_set_id', 'name']);
        });

        Schema::create('unit_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leader_unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('bodyguard_unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('kind', 16);

            $table->unique(['leader_unit_id', 'bodyguard_unit_id', 'kind']);
        });

        Schema::create('army_list_detachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('army_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('detachment_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('position')->default(0);

            $table->unique(['army_list_id', 'detachment_id']);
        });

        DB::table('army_lists')->whereNotNull('detachment_id')->orderBy('id')->each(function (object $armyList): void {
            DB::table('army_list_detachments')->insert(['army_list_id' => $armyList->id, 'detachment_id' => $armyList->detachment_id, 'position' => 0]);
        });

        Schema::table('army_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('detachment_id');
        });

        Schema::table('army_list_entries', function (Blueprint $table) {
            $table->foreignId('attached_to_entry_id')->nullable()->after('enhancement_id')->constrained('army_list_entries')->nullOnDelete();
        });

        Schema::create('army_list_entry_wargear', function (Blueprint $table) {
            $table->id();
            $table->foreignId('army_list_entry_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('quantity');

            $table->unique(['army_list_entry_id', 'name']);
        });
    }

    /**
     * Reverse the migrations. Only the first detachment of each list is kept.
     */
    public function down(): void
    {
        Schema::dropIfExists('army_list_entry_wargear');

        Schema::table('army_list_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attached_to_entry_id');
        });

        Schema::table('army_lists', function (Blueprint $table) {
            $table->foreignId('detachment_id')->nullable()->after('faction_id')->constrained()->restrictOnDelete();
        });

        DB::table('army_list_detachments')->orderBy('position')->get()->unique('army_list_id')->each(function (object $row): void {
            DB::table('army_lists')->where('id', $row->army_list_id)->update(['detachment_id' => $row->detachment_id]);
        });

        Schema::dropIfExists('army_list_detachments');
        Schema::dropIfExists('unit_attachments');
        Schema::dropIfExists('unit_wargear_costs');

        DB::table('unit_point_costs')->where('from_copy', '>', 1)->delete();
        Schema::table('unit_point_costs', function (Blueprint $table) {
            $table->unique(['unit_id', 'point_set_id', 'model_count']);
        });
        Schema::table('unit_point_costs', function (Blueprint $table) {
            $table->dropUnique('unit_point_costs_unique_tier');
            $table->dropColumn('from_copy');
        });

        Schema::table('enhancements', function (Blueprint $table) {
            $table->dropColumn('is_upgrade');
        });

        Schema::table('detachments', function (Blueprint $table) {
            $table->dropColumn(['detachment_points', 'unique_tag', 'force_dispositions']);
        });
    }
};
