<?php

namespace Tests\Feature;

use App\Support\LocalizedText;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class LocalizedTextTest extends TestCase
{
    public function test_it_returns_the_current_locale_text(): void
    {
        app()->setLocale('fr');

        $text = new LocalizedText(['en' => 'Terminator Squad', 'fr' => 'Escouade Terminator']);

        $this->assertSame('Escouade Terminator', $text->get());
        $this->assertSame('Escouade Terminator', (string) $text);
        $this->assertSame('Terminator Squad', $text->get('en'));
    }

    public function test_it_falls_back_to_english_when_a_translation_is_missing_or_empty(): void
    {
        app()->setLocale('fr');

        $this->assertSame('Intercessors', (new LocalizedText(['en' => 'Intercessors']))->get());
        $this->assertSame('Intercessors', (new LocalizedText(['en' => 'Intercessors', 'fr' => '']))->get());
        $this->assertFalse((new LocalizedText(['en' => 'Intercessors', 'fr' => '']))->has('fr'));
    }

    public function test_the_cast_stores_readable_json_and_reads_it_back(): void
    {
        $model = $this->makeModel();

        $model->name = ['en' => 'Primed', 'fr' => 'Sous-couché'];

        $this->assertSame('{"en":"Primed","fr":"Sous-couché"}', $model->getAttributes()['name']);
        $this->assertInstanceOf(LocalizedText::class, $model->name);
        $this->assertSame('Sous-couché', $model->name->get('fr'));
    }

    public function test_the_cast_stores_a_plain_string_as_english_and_keeps_null(): void
    {
        $model = $this->makeModel();

        $model->name = 'Intercessors';
        $this->assertSame('{"en":"Intercessors"}', $model->getAttributes()['name']);

        $model->name = null;
        $this->assertNull($model->getAttributes()['name']);
        $this->assertNull($model->name);
    }

    private function makeModel(): Model
    {
        return new class extends Model
        {
            protected function casts(): array
            {
                return ['name' => LocalizedText::class];
            }
        };
    }
}
