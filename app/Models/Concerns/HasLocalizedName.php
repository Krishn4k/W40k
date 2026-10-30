<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reference records whose "name" column is a JSON object of translations ({"en": …, "fr": …}),
 * searched in SQL ignoring case and accents.
 */
trait HasLocalizedName
{
    /**
     * SQL expression of the name in one language (NULL when it has no translation in that language).
     */
    public static function nameSql(string $locale): string
    {
        $locale = preg_replace('/[^a-z]/', '', $locale) ?: 'en';

        return "JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$locale}'))";
    }

    /**
     * Names containing the text, in English or in French.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function whereNameContains(Builder $query, string $text): void
    {
        $pattern = '%'.addcslashes($text, '\\%_').'%';

        $query->where(fn (Builder $query) => $query
            ->whereRaw(static::nameSql('en').' COLLATE utf8mb4_unicode_ci LIKE ?', [$pattern])
            ->orWhereRaw(static::nameSql('fr').' COLLATE utf8mb4_unicode_ci LIKE ?', [$pattern]));
    }

    /**
     * Records whose English name is exactly this one, ignoring case and accents.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function whereEnglishName(Builder $query, string $name): void
    {
        $query->whereRaw(static::nameSql('en').' COLLATE utf8mb4_unicode_ci = ?', [$name]);
    }
}
