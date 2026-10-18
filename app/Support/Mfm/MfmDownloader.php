<?php

namespace App\Support\Mfm;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Downloads the Munitorum Field Manual YAML files (one per faction, plus meta.yaml).
 */
final class MfmDownloader
{
    public function __construct(
        private readonly string $repository,
        private readonly string $directory,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('bsdata.mfm_repository'), config('bsdata.mfm_directory'));
    }

    /**
     * Replace the local files with the repository's current ones.
     *
     * @return list<string> Downloaded file names.
     */
    public function download(): array
    {
        $files = Http::acceptJson()
            ->timeout(30)
            ->retry(2, 1000)
            ->get("https://api.github.com/repos/{$this->repository}/contents/data")
            ->throw()
            ->collect()
            ->filter(fn (array $file): bool => ($file['type'] ?? null) === 'file' && str_ends_with($file['name'], '.yaml'))
            ->values();

        File::ensureDirectoryExists($this->directory);

        foreach (File::glob($this->directory.'/*.yaml') as $stale) {
            File::delete($stale);
        }

        foreach ($files as $file) {
            File::put($this->directory.'/'.basename($file['name']), Http::timeout(60)->retry(2, 1000)->get($file['download_url'])->throw()->body());
        }

        return $files->pluck('name')->all();
    }
}
