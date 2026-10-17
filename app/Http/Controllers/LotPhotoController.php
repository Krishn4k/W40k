<?php

namespace App\Http\Controllers;

use App\Models\CollectionLot;
use App\Models\LotPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photos of a collection lot, stored on the private disk and served only to the lot's owner.
 */
class LotPhotoController extends Controller
{
    /**
     * Maximum size of an uploaded photo, in kilobytes.
     */
    private const MAX_SIZE_KB = 10240;

    public function store(Request $request, CollectionLot $lot): RedirectResponse
    {
        Gate::authorize('update', $lot);

        $validated = $request->validate([
            'photos' => ['required', 'array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_SIZE_KB],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $position = (int) $lot->photos()->max('position');

        foreach ($validated['photos'] as $file) {
            /** @var UploadedFile $file */
            $lot->photos()->create([
                'path' => $file->store("lot-photos/{$lot->id}", 'local'),
                'caption' => $validated['caption'] ?? null,
                'position' => ++$position,
            ]);
        }

        return redirect()->route('collection.edit', $lot)->with('status', count($validated['photos']).' photo(s) ajoutée(s).');
    }

    public function show(CollectionLot $lot, LotPhoto $photo): StreamedResponse
    {
        Gate::authorize('view', $lot);

        return Storage::disk('local')->response($photo->path);
    }

    public function destroy(CollectionLot $lot, LotPhoto $photo): RedirectResponse
    {
        Gate::authorize('update', $lot);

        $photo->delete();

        return redirect()->route('collection.edit', $lot)->with('status', 'Photo supprimée.');
    }
}
