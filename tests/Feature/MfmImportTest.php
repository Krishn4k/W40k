<?php

namespace Tests\Feature;

use App\Models\Detachment;
use App\Models\Enhancement;
use App\Models\PointSet;
use App\Models\Unit;
use App\Models\UnitAttachment;
use App\Support\BsData\BsDataImporter;
use App\Support\BsData\BsDataLibrary;
use App\Support\Mfm\MfmImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MfmImportTest extends TestCase
{
    use RefreshDatabase;

    private PointSet $pointSet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pointSet = (new BsDataImporter(new BsDataLibrary(base_path('tests/Fixtures/bsdata'))))->import()['pointSet'];
    }

    public function test_unit_costs_follow_requisition_thresholds(): void
    {
        $this->import();

        $intercessors = Unit::query()->where('bsdata_id', 'unit-intercessors')->sole();

        $this->assertSame([
            [5, 1, 75], [5, 3, 85], [10, 1, 150], [10, 3, 170],
        ], $intercessors->pointCosts()->whereBelongsTo($this->pointSet)->orderBy('model_count')->orderBy('from_copy')->get()
            ->map(fn ($cost): array => [$cost->model_count, $cost->from_copy, $cost->points])->all());
    }

    public function test_chapter_files_do_not_reprice_the_parent_units(): void
    {
        $this->import();

        $captain = Unit::query()->where('bsdata_id', 'unit-captain')->sole();
        $guard = Unit::query()->where('bsdata_id', 'unit-guard')->sole();

        $this->assertSame([1 => 85], $captain->pointCosts()->whereBelongsTo($this->pointSet)->pluck('points', 'model_count')->all());
        $this->assertSame([3 => 120, 6 => 240], $guard->pointCosts()->whereBelongsTo($this->pointSet)->orderBy('model_count')->pluck('points', 'model_count')->all());
    }

    public function test_wargear_costs_and_leader_attachments_are_imported(): void
    {
        $this->import();

        $captain = Unit::query()->where('bsdata_id', 'unit-captain')->sole();
        $intercessors = Unit::query()->where('bsdata_id', 'unit-intercessors')->sole();

        $this->assertSame(['Relic Shield' => 5], $captain->wargearCosts()->whereBelongsTo($this->pointSet)->pluck('points', 'name')->all());
        $this->assertSame(
            [[$intercessors->id, UnitAttachment::LEADER]],
            $captain->attachments->map(fn (UnitAttachment $attachment): array => [$attachment->bodyguard_unit_id, $attachment->kind])->all(),
        );
    }

    public function test_detachments_get_their_points_tags_and_enhancements(): void
    {
        $result = $this->import();

        $gladius = Detachment::query()->where('bsdata_id', 'det-gladius')->sole();
        $this->assertSame(3, $gladius->detachment_points);
        $this->assertNull($gladius->unique_tag);
        $this->assertSame(['TAKE AND HOLD', 'PRIORITY ASSETS'], $gladius->force_dispositions);

        $artificer = Enhancement::query()->where('bsdata_id', 'enh-artificer')->sole();
        $this->assertSame(15, $artificer->pointCosts()->whereBelongsTo($this->pointSet)->value('points'));
        $this->assertTrue($gladius->enhancements()->where('name->en', 'Laurels of Triumph')->exists());

        $ironstorm = Detachment::query()->where('name->en', 'Ironstorm Spearhead')->sole();
        $this->assertSame('Ironstorm', $ironstorm->unique_tag);
        $gunnery = $ironstorm->enhancements()->sole();
        $this->assertSame('Gunnery Honours', $gunnery->name->get('en'));
        $this->assertTrue($gunnery->is_upgrade);

        $this->assertSame(1, $result['counts']['createdDetachments']);
        $this->assertSame(2, $result['counts']['createdEnhancements']);
    }

    public function test_an_enhancement_spelled_with_its_upgrade_tag_is_matched_and_earlier_duplicates_removed(): void
    {
        $artificer = Enhancement::query()->where('bsdata_id', 'enh-artificer')->sole();
        $artificer->update(['name' => ['en' => 'Artificer Armour (Upgrade)', 'fr' => 'Armure d\'artificier']]);
        $duplicate = Enhancement::query()->create(['detachment_id' => $artificer->detachment_id, 'name' => ['en' => 'Artificer Armour']]);

        $result = $this->import();

        $this->assertSame(['en' => 'Artificer Armour', 'fr' => 'Armure d\'artificier'], $artificer->fresh()->name->toArray());
        $this->assertModelMissing($duplicate);
        $this->assertSame(1, $result['counts']['deletedDuplicateEnhancements']);
    }

    public function test_unknown_units_and_factions_are_reported_and_the_point_set_names_the_version(): void
    {
        $result = $this->import();

        $this->assertSame(1, $result['counts']['unmatchedUnits']);
        $this->assertContains('Unité MFM « Thunderhawk Gunship » (Space Marines) introuvable.', $result['warnings']);
        $this->assertContains('Faction MFM « Chaos Daemons » absente du référentiel : fichier ignoré.', $result['warnings']);
        $this->assertStringEndsWith(' · MFM 1.5', $this->pointSet->refresh()->name);
    }

    public function test_importing_again_keeps_a_single_point_set_and_no_duplicates(): void
    {
        $this->import();
        $pointSet = (new BsDataImporter(new BsDataLibrary(base_path('tests/Fixtures/bsdata'))))->import()['pointSet'];
        $this->import();

        $this->assertTrue($pointSet->is($this->pointSet));
        $this->assertSame(1, PointSet::query()->where('name', 'like', 'BSData%')->count());
        $this->assertSame(1, Detachment::query()->where('name->en', 'Ironstorm Spearhead')->count());
        $this->assertSame(1, UnitAttachment::query()->count());
    }

    public function test_the_command_applies_the_downloaded_field_manual(): void
    {
        config([
            'bsdata.directory' => base_path('tests/Fixtures/bsdata'),
            'bsdata.mfm_directory' => base_path('tests/Fixtures/mfm'),
        ]);

        $this->artisan('bsdata:import', ['--offline' => true, '--without-translations' => true])
            ->expectsOutputToContain('Application des points officiels (MFM)')
            ->expectsOutputToContain('· MFM 1.5 ».')
            ->assertSuccessful();
    }

    /**
     * @return array{counts: array<string, int>, warnings: list<string>}
     */
    private function import(): array
    {
        return (new MfmImporter(base_path('tests/Fixtures/mfm')))->import($this->pointSet);
    }
}
