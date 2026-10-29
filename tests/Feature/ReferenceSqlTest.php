<?php

namespace Tests\Feature;

use App\Models\CollectionLot;
use App\Models\Faction;
use App\Models\Keyword;
use App\Models\PointSet;
use App\Models\TranslationReview;
use App\Models\Unit;
use App\Models\User;
use App\Support\ReferenceSqlExport;
use App\Support\ReferenceSqlImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class ReferenceSqlTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_export_restores_the_reference_and_keeps_what_personal_data_uses(): void
    {
        $pointSet = PointSet::factory()->create();
        $faction = Faction::factory()->create(['name' => ['en' => 'Orks', 'fr' => 'Orks']]);
        $boyz = Unit::factory()->for($faction)->create(['name' => ['en' => 'Boyz', 'fr' => 'Boyz validés en ligne']]);
        $boyz->keywords()->attach(Keyword::factory()->create());
        $boyz->pointCosts()->create(['point_set_id' => $pointSet->id, 'model_count' => 10, 'points' => 85]);
        TranslationReview::create(['translatable_type' => 'unit', 'translatable_id' => $boyz->id]);

        $sql = (new ReferenceSqlExport(''))->toSql();

        // What happens on the destination since: a name changed, costs lost, two units the source no longer has.
        $boyz->update(['name' => ['en' => 'Boyz', 'fr' => 'Ancien nom']]);
        $boyz->pointCosts()->delete();
        $obsolete = Unit::factory()->for($faction)->create(['name' => ['en' => 'Obsolete']]);
        $owned = Unit::factory()->for($faction)->create(['name' => ['en' => 'Owned']]);
        $lot = CollectionLot::factory()->for(User::factory())->for($owned)->create();

        $statements = (new ReferenceSqlImport)->fromSql($sql);

        $this->assertGreaterThan(5, $statements);
        $this->assertSame('Boyz validés en ligne', $boyz->fresh()->name->get('fr'));
        $this->assertSame([10 => 85], $boyz->pointCosts()->pluck('points', 'model_count')->all());
        $this->assertSame(1, $boyz->keywords()->count());
        $this->assertSame(1, TranslationReview::query()->count());
        $this->assertModelMissing($obsolete);
        $this->assertModelExists($owned);
        $this->assertTrue($lot->fresh()->unit->is($owned), 'Personal data is untouched.');
    }

    public function test_the_script_targets_the_requested_prefix_and_is_refused_elsewhere(): void
    {
        Faction::factory()->create();

        $sql = (new ReferenceSqlExport('w40k_'))->toSql();

        $this->assertStringStartsWith("-- target-prefix: w40k_\n", $sql);
        $this->assertStringContainsString('INSERT INTO `w40k_factions`', $sql);
        $this->assertStringContainsString('DELETE FROM `w40k_unit_point_costs`;', $sql);
        $this->assertStringNotContainsString('INSERT INTO `factions`', $sql);
        $this->assertStringNotContainsString('`w40k_users`', $sql);

        $this->expectException(RuntimeException::class);
        (new ReferenceSqlImport)->fromSql($sql);
    }

    public function test_the_commands_write_then_load_a_compressed_file(): void
    {
        Faction::factory()->create(['name' => ['en' => 'Necrons']]);
        $path = storage_path('framework/testing/reference-test.sql.gz');

        $this->artisan('reference:export-sql', ['--prefix' => '', '--output' => $path])
            ->expectsOutputToContain('Référentiel exporté')
            ->assertSuccessful();
        $this->assertStringStartsWith('-- target-prefix:', gzdecode(File::get($path)));

        Faction::query()->update(['name' => ['en' => 'Renamed']]);

        $this->artisan('reference:import-sql', ['file' => $path, '--force' => true])
            ->expectsOutputToContain('Référentiel importé')
            ->assertSuccessful();
        $this->assertSame('Necrons', Faction::query()->sole()->name->get('en'));

        File::delete($path);
    }

    public function test_admins_download_the_reference_for_a_workstation(): void
    {
        Faction::factory()->create(['name' => ['en' => 'Aeldari']]);

        $this->actingAs(User::factory()->create())->get(route('admin.maintenance.reference'))->assertForbidden();

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.maintenance.reference'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/gzip');

        $sql = gzdecode($response->getContent());
        $this->assertSame('', ReferenceSqlExport::targetPrefixOf($sql));
        $this->assertStringContainsString('Aeldari', $sql);
    }
}
