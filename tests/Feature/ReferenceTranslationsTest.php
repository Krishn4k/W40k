<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Keyword;
use App\Models\Unit;
use App\Support\ReferenceTranslations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReferenceTranslationsTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/translations.csv');
        File::ensureDirectoryExists(dirname($this->path));
    }

    protected function tearDown(): void
    {
        File::delete($this->path);

        parent::tearDown();
    }

    public function test_export_writes_a_spreadsheet_friendly_csv_of_bsdata_records(): void
    {
        Faction::factory()->create(['bsdata_id' => 'cat-sm', 'name' => ['en' => 'Space Marines', 'fr' => 'Space Marines']]);
        $unit = Unit::factory()->create(['bsdata_id' => 'unit-guard', 'name' => ['en' => 'Sanguinary Guard', 'fr' => 'Garde Sanguinienne']]);
        $unit->markTranslationToReview(true);
        Keyword::factory()->create(['bsdata_id' => 'kw-infantry', 'name' => ['en' => 'Infantry']]);
        Unit::factory()->create(['bsdata_id' => null, 'name' => ['en' => 'Hand-made unit']]);

        $rows = (new ReferenceTranslations)->export($this->path);

        $content = File::get($this->path);
        $this->assertStringStartsWith("\u{FEFF}type;bsdata_id;en;fr;statut", $content);
        $this->assertStringContainsString('unit;unit-guard;"Sanguinary Guard";"Garde Sanguinienne";"à vérifier"', $content);
        $this->assertStringContainsString('faction;cat-sm;"Space Marines";"Space Marines";vérifié', $content);
        $this->assertStringContainsString('keyword;kw-infantry;Infantry;;', $content);
        $this->assertStringNotContainsString('Hand-made unit', $content);
        $this->assertSame(3, $rows, 'Faction, unit and keyword rows.');
    }

    public function test_export_can_be_limited_to_missing_translations(): void
    {
        Unit::factory()->create(['bsdata_id' => 'u-1', 'name' => ['en' => 'Translated', 'fr' => 'Traduit']]);
        Unit::factory()->create(['bsdata_id' => 'u-2', 'name' => ['en' => 'Missing']]);

        (new ReferenceTranslations)->export($this->path, missingOnly: true);

        $this->assertStringContainsString('Missing', File::get($this->path));
        $this->assertStringNotContainsString('Translated', File::get($this->path));
    }

    public function test_import_applies_names_and_statuses_and_ignores_empty_names(): void
    {
        $unit = Unit::factory()->create(['bsdata_id' => 'unit-guard', 'name' => ['en' => 'Sanguinary Guard']]);
        $keyword = Keyword::factory()->create(['bsdata_id' => 'kw-infantry', 'name' => ['en' => 'Infantry', 'fr' => 'Infanterie']]);
        $keyword->markTranslationToReview(true);
        $untouched = Faction::factory()->create(['bsdata_id' => 'cat-sm', 'name' => ['en' => 'Space Marines', 'fr' => 'Space Marines']]);

        File::put($this->path, "\u{FEFF}type;bsdata_id;en;fr;statut\n"
            ."unit;unit-guard;Sanguinary Guard;Garde Sanguinienne;à vérifier\n"
            ."keyword;kw-infantry;Infantry;Infanterie;vérifié\n"
            ."faction;cat-sm;Space Marines;;\n"
            ."unit;unknown-id;Ghost;Fantôme;vérifié\n"
            ."spell;x;Smite;Châtiment;vérifié\n");

        $report = (new ReferenceTranslations)->import($this->path);

        $this->assertSame(['updated' => 2, 'unchanged' => 1, 'unknown' => 2], $report);
        $this->assertSame('Garde Sanguinienne', $unit->fresh()->name->get('fr'));
        $this->assertSame('Sanguinary Guard', $unit->fresh()->name->get('en'));
        $this->assertNotNull($unit->fresh()->translationReview);
        $this->assertNull($keyword->fresh()->translationReview);
        $this->assertSame('Space Marines', $untouched->fresh()->name->get('fr'));
    }

    public function test_an_export_can_be_imported_back_unchanged(): void
    {
        $unit = Unit::factory()->create(['bsdata_id' => 'unit-guard', 'name' => ['en' => 'Sanguinary Guard', 'fr' => 'Garde Sanguinienne']]);
        $unit->markTranslationToReview(true);
        $translations = new ReferenceTranslations;

        $translations->export($this->path);
        $translations->import($this->path);

        $this->assertSame('Garde Sanguinienne', $unit->fresh()->name->get('fr'));
        $this->assertNotNull($unit->fresh()->translationReview);
    }

    public function test_the_commands_export_and_import(): void
    {
        Unit::factory()->create(['bsdata_id' => 'unit-guard', 'name' => ['en' => 'Sanguinary Guard']]);

        $this->artisan('reference:export-translations', ['path' => $this->path])
            ->expectsOutputToContain('1 ligne(s) exportée(s)')
            ->assertSuccessful();

        File::put($this->path, str_replace('"Sanguinary Guard";;', '"Sanguinary Guard";"Garde Sanguinienne";vérifié', File::get($this->path)));

        $this->artisan('reference:import-translations', ['path' => $this->path])
            ->expectsOutputToContain('1 traduction(s) appliquée(s)')
            ->assertSuccessful();

        $this->artisan('reference:import-translations', ['path' => storage_path('missing.csv')])
            ->expectsOutputToContain('Fichier de traductions introuvable')
            ->assertFailed();
    }

    public function test_the_versioned_prefill_file_is_valid(): void
    {
        $content = File::get(ReferenceTranslations::defaultPath());

        $this->assertStringStartsWith("\u{FEFF}type;bsdata_id;en;fr;statut", $content);
        $this->assertGreaterThan(400, substr_count($content, 'à vérifier'));
    }
}
