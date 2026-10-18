<?php

namespace Tests\Feature;

use App\Enums\UnitRole;
use App\Models\ArmyListEntry;
use App\Models\Detachment;
use App\Models\Enhancement;
use App\Models\Faction;
use App\Models\Keyword;
use App\Models\PointSet;
use App\Models\Unit;
use App\Support\BsData\BsDataImporter;
use App\Support\BsData\BsDataLibrary;
use Database\Seeders\PointSetSeeder;
use Database\Seeders\SpaceMarinesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BsDataImportTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = base_path('tests/Fixtures/bsdata');
    }

    public function test_factions_and_sub_factions_are_imported(): void
    {
        $this->import();

        $spaceMarines = Faction::query()->where('bsdata_id', 'cat-sm')->sole();
        $bloodAngels = Faction::query()->where('bsdata_id', 'cat-ba')->sole();

        $this->assertSame('Space Marines', $spaceMarines->name->get('en'));
        $this->assertNull($spaceMarines->parent_id);
        $this->assertTrue($bloodAngels->parent->is($spaceMarines));
        $this->assertSame('blood-angels', $bloodAngels->slug);
    }

    public function test_units_get_their_role_keywords_and_squad_size_costs(): void
    {
        $result = $this->import();
        $pointSet = $result['pointSet'];

        $intercessors = Unit::query()->where('bsdata_id', 'unit-intercessors')->sole();
        $this->assertSame(UnitRole::Battleline, $intercessors->role);
        $this->assertSame([5 => 80, 10 => 160], $this->costs($intercessors, $pointSet));
        $this->assertEqualsCanonicalizing(['Battleline', 'Infantry'], $intercessors->keywords->map(fn ($keyword) => $keyword->name->get('en'))->all());

        $guard = Unit::query()->where('bsdata_id', 'unit-guard')->sole();
        $this->assertSame([3 => 125, 6 => 260], $this->costs($guard, $pointSet));
        $this->assertSame('cat-ba', $guard->faction->bsdata_id);

        $dante = Unit::query()->where('bsdata_id', 'unit-dante')->sole();
        $this->assertTrue($dante->is_epic_hero);
        $this->assertSame(UnitRole::Character, $dante->role);
        $this->assertSame([1 => 120], $this->costs($dante, $pointSet));

        $this->assertSame([1 => 70], $this->costs(Unit::query()->where('bsdata_id', 'unit-storm')->sole(), $pointSet), 'Cost carried by the root link.');
        $this->assertSame(1, $result['counts']['skippedCostModifiers'], 'The detachment-dependent increment is skipped.');
    }

    public function test_the_eleventh_edition_json_format_is_read_like_the_xml_one(): void
    {
        $result = (new BsDataImporter(new BsDataLibrary(base_path('tests/Fixtures/bsdata-json'))))->import();

        $guard = Unit::query()->where('bsdata_id', 'unit-guard-json')->sole();
        $this->assertSame('Blood Angels', $guard->faction->name->get('en'));
        $this->assertSame([3 => 135, 6 => 275], $this->costs($guard, $result['pointSet']));
        $this->assertSame(['Infantry'], $guard->keywords->map(fn ($keyword) => $keyword->name->get('en'))->all());
        $this->assertSame('BSData Warhammer 40,000 11th Edition (rév. 3)', $result['pointSet']->name);
    }

    public function test_library_units_belong_to_the_closest_importing_faction(): void
    {
        $result = $this->import();

        $guardians = Unit::query()->where('bsdata_id', 'unit-guardians')->sole();
        $this->assertSame('cat-craftworlds', $guardians->faction->bsdata_id);
        $this->assertSame([10 => 100, 11 => 110, 12 => 120], $this->costs($guardians, $result['pointSet']), 'Per-model costs.');
    }

    public function test_a_library_shared_by_unrelated_factions_becomes_its_own_faction(): void
    {
        $this->import();

        $titans = Faction::query()->where('bsdata_id', 'cat-titans')->sole();
        $this->assertSame('Titans', $titans->name->get('en'));
        $this->assertTrue(Unit::query()->where('bsdata_id', 'unit-warlord')->sole()->faction->is($titans));
    }

    public function test_detachments_and_enhancements_are_attached_to_their_faction(): void
    {
        $result = $this->import();

        $gladius = Detachment::query()->where('bsdata_id', 'det-gladius')->sole();
        $liberator = Detachment::query()->where('bsdata_id', 'det-liberator')->sole();
        $this->assertSame('cat-sm', $gladius->faction->bsdata_id);
        $this->assertSame('cat-ba', $liberator->faction->bsdata_id);

        $foresight = Enhancement::query()->where('bsdata_id', 'enh-foresight')->sole();
        $this->assertTrue($foresight->detachment->is($liberator));
        $this->assertSame(15, $foresight->pointCosts()->whereBelongsTo($result['pointSet'])->sole()->points);
        $this->assertDatabaseMissing('enhancements', ['bsdata_id' => 'enh-orphan']);

        $flat = Enhancement::query()->where('bsdata_id', 'enh-flat-veil')->sole();
        $this->assertTrue($flat->detachment->is($gladius), 'A flat enhancement goes to the detachment its visibility depends on.');
        $this->assertSame(20, $flat->pointCosts()->whereBelongsTo($result['pointSet'])->sole()->points);
        $this->assertStringContainsString('Missing Detachment Enhancements', implode("\n", $result['warnings']));
    }

    public function test_importing_again_updates_without_duplicating_and_keeps_french_names(): void
    {
        $this->import();
        $captain = Unit::query()->where('bsdata_id', 'unit-captain')->sole();
        $captain->update(['name' => ['en' => 'Captain', 'fr' => 'Capitaine']]);

        $this->import();

        $this->assertSame(1, Unit::query()->where('bsdata_id', 'unit-captain')->count());
        $this->assertSame('Capitaine', $captain->fresh()->name->get('fr'));
        $this->assertSame(1, PointSet::count());
        $this->assertSame(1, $captain->pointCosts()->count());
    }

    public function test_seeded_test_data_is_reused_then_pruned_when_unused(): void
    {
        $this->seed([PointSetSeeder::class, SpaceMarinesSeeder::class]);
        $seededFaction = Faction::query()->where('slug', 'space-marines')->sole();
        $usedSeededUnit = Unit::query()->whereNull('bsdata_id')->first();
        ArmyListEntry::factory()->for($usedSeededUnit)->create();

        $result = $this->import(prune: true);

        $this->assertSame('cat-sm', $seededFaction->fresh()->bsdata_id, 'The seeded faction is matched by slug.');
        $this->assertSame(1, Faction::query()->where('slug', 'space-marines')->count());
        $this->assertModelExists($usedSeededUnit);
        $this->assertSame(1, Unit::query()->whereNull('bsdata_id')->count(), 'Only the unit used in a list is kept.');
        $this->assertSame(0, Detachment::query()->whereNull('bsdata_id')->count());
        $this->assertNull(PointSet::query()->where('name', 'Données de test 2026')->first());
        $this->assertTrue(Keyword::query()->where('bsdata_id', 'cat-infantry')->exists());
        $this->assertGreaterThan(0, $result['counts']['prunedUnits']);
    }

    public function test_the_command_downloads_the_files_then_imports_them(): void
    {
        $directory = storage_path('framework/testing/bsdata');
        File::deleteDirectory($directory);
        config(['bsdata.directory' => $directory, 'bsdata.repository' => 'BSData/test']);

        $files = collect(File::files($this->fixtures))->map(fn ($file) => $file->getFilename());
        Http::fake([
            'api.github.com/repos/BSData/test/contents*' => Http::response($files->map(fn (string $name) => [
                'name' => $name,
                'type' => 'file',
                'download_url' => 'https://raw.example.test/'.rawurlencode($name),
            ])->push(['name' => 'README.md', 'type' => 'file', 'download_url' => 'https://raw.example.test/README.md'])->all()),
            'raw.example.test/*' => fn ($request) => Http::response(File::get($this->fixtures.'/'.rawurldecode(basename($request->url())))),
        ]);

        $this->artisan('bsdata:import')
            ->expectsOutputToContain('7 fichier(s) téléchargé(s).')
            ->expectsOutputToContain('Points enregistrés dans la version « BSData Warhammer 40,000 10th Edition (rév. 42) ».')
            ->assertSuccessful();

        $this->assertTrue(Unit::query()->where('bsdata_id', 'unit-intercessors')->exists());
        $this->assertFileDoesNotExist($directory.'/README.md');

        File::deleteDirectory($directory);
    }

    public function test_the_command_reports_a_missing_directory(): void
    {
        config(['bsdata.directory' => storage_path('framework/testing/missing-bsdata')]);

        $this->artisan('bsdata:import', ['--offline' => true])
            ->expectsOutputToContain('Aucun fichier BSData')
            ->assertFailed();
    }

    /**
     * @return array{pointSet: PointSet, counts: array<string, int>, warnings: list<string>}
     */
    private function import(bool $prune = false): array
    {
        return (new BsDataImporter(new BsDataLibrary($this->fixtures)))->import($prune);
    }

    /**
     * @return array<int, int>
     */
    private function costs(Unit $unit, PointSet $pointSet): array
    {
        return $unit->pointCosts()->whereBelongsTo($pointSet)->orderBy('model_count')->pluck('points', 'model_count')->all();
    }
}
