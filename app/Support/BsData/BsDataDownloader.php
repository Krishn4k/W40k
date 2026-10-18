<?php

namespace App\Support\BsData;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Downloads the game system and catalogue files of the configured BSData repository.
 */
final class BsDataDownloader
{
    public function __construct(
        private readonly string $repository,
        private readonly string $branch,
        private readonly string $directory,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('bsdata.repository'), config('bsdata.branch'), config('bsdata.directory'));
    }

    /**
     * Replace the local files with the repository's current ones.
     *
     * @param  (callable(string): void)|null  $onFile  Called with each downloaded file name.
     * @return list<string> Downloaded file names.
     */
    public function download(?callable $onFile = null): array
    {
        $files = Http::acceptJson()
            ->timeout(30)
            ->retry(2, 1000)
            ->get("https://api.github.com/repos/{$this->repository}/contents", ['ref' => $this->branch])
            ->throw()
            ->collect()
            ->filter(fn (array $file): bool => ($file['type'] ?? null) === 'file' && preg_match('/\.(cat|gst|json)$/', $file['name']) === 1)
            ->values();

        File::ensureDirectoryExists($this->directory);

        // GLOB_BRACE is not available on Windows: one pattern per extension.
        foreach ([...File::glob($this->directory.'/*.cat'), ...File::glob($this->directory.'/*.gst'), ...File::glob($this->directory.'/*.json')] as $stale) {
            File::delete($stale);
        }

        foreach ($files as $file) {
            $content = Http::timeout(120)->retry(2, 1000)->get($file['download_url'])->throw()->body();
            File::put($this->directory.'/'.basename($file['name']), $content);

            if ($onFile) {
                $onFile($file['name']);
            }
        }

        return $files->pluck('name')->all();
    }
}
