<?php

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use Stringable;

/**
 * Reference data text stored as JSON ({"en": "...", "fr": "..."}), English being the reference language.
 */
final class LocalizedText implements Castable, JsonSerializable, Stringable
{
    public const REFERENCE_LOCALE = 'en';

    /**
     * @var array<string, string>
     */
    private readonly array $translations;

    /**
     * @param  array<string, string|null>  $translations
     */
    public function __construct(array $translations)
    {
        $this->translations = array_filter($translations, fn (?string $text): bool => filled($text));
    }

    /**
     * Text in the given locale (current locale by default), falling back to English.
     */
    public function get(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->translations[$locale]
            ?? $this->translations[self::REFERENCE_LOCALE]
            ?? '';
    }

    public function has(string $locale): bool
    {
        return array_key_exists($locale, $this->translations);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->translations;
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->translations;
    }

    public function __toString(): string
    {
        return $this->get();
    }

    /**
     * @param  array<int, string>  $arguments
     * @return CastsAttributes<LocalizedText, LocalizedText|array<string, string|null>|string>
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get(Model $model, string $key, mixed $value, array $attributes): ?LocalizedText
            {
                if ($value === null) {
                    return null;
                }

                return new LocalizedText(json_decode($value, true, flags: JSON_THROW_ON_ERROR));
            }

            public function set(Model $model, string $key, mixed $value, array $attributes): ?string
            {
                if ($value === null) {
                    return null;
                }

                $translations = match (true) {
                    $value instanceof LocalizedText => $value->toArray(),
                    is_string($value) => [LocalizedText::REFERENCE_LOCALE => $value],
                    default => (new LocalizedText($value))->toArray(),
                };

                return json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
        };
    }
}
