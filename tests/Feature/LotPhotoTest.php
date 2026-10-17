<?php

namespace Tests\Feature;

use App\Models\CollectionLot;
use App\Models\LotPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LotPhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CollectionLot $lot;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->withoutVite();
        $this->user = User::factory()->create();
        $this->lot = CollectionLot::factory()->for($this->user)->create();
    }

    public function test_photos_are_stored_privately_and_listed_on_the_lot(): void
    {
        $response = $this->actingAs($this->user)->post(route('collection.photos.store', $this->lot), [
            'photos' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.png')],
            'caption' => 'Escouade terminée',
        ]);

        $response->assertRedirect(route('collection.edit', $this->lot));
        $photos = $this->lot->photos()->get();
        $this->assertCount(2, $photos);
        $this->assertSame([1, 2], $photos->pluck('position')->all());
        Storage::disk('local')->assertExists($photos->pluck('path')->all());

        $this->actingAs($this->user)->get(route('collection.edit', $this->lot))->assertSee('Escouade terminée');
    }

    public function test_only_images_are_accepted(): void
    {
        $this->actingAs($this->user)->post(route('collection.photos.store', $this->lot), [
            'photos' => [UploadedFile::fake()->create('list.pdf', 100, 'application/pdf')],
        ])->assertSessionHasErrors('photos.0');

        $this->assertSame(0, LotPhoto::count());
    }

    public function test_a_photo_is_only_served_to_the_owner_of_the_lot(): void
    {
        $photo = $this->storePhoto();

        $this->actingAs($this->user)->get(route('collection.photos.show', [$this->lot, $photo]))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('collection.photos.show', [$this->lot, $photo]))->assertForbidden();
    }

    public function test_a_photo_cannot_be_reached_through_another_lot(): void
    {
        $photo = $this->storePhoto();
        $otherLot = CollectionLot::factory()->for($this->user)->create();

        $this->actingAs($this->user)->get(route('collection.photos.show', [$otherLot, $photo]))->assertNotFound();
    }

    public function test_deleting_a_photo_removes_its_file(): void
    {
        $photo = $this->storePhoto();

        $this->actingAs($this->user)->delete(route('collection.photos.destroy', [$this->lot, $photo]))
            ->assertRedirect(route('collection.edit', $this->lot));

        $this->assertModelMissing($photo);
        Storage::disk('local')->assertMissing($photo->path);
    }

    public function test_deleting_a_lot_removes_its_photo_files(): void
    {
        $photo = $this->storePhoto();

        $this->actingAs($this->user)->delete(route('collection.destroy', $this->lot));

        Storage::disk('local')->assertMissing($photo->path);
    }

    public function test_another_user_cannot_add_photos(): void
    {
        $this->actingAs(User::factory()->create())->post(route('collection.photos.store', $this->lot), [
            'photos' => [UploadedFile::fake()->image('front.jpg')],
        ])->assertForbidden();
    }

    private function storePhoto(): LotPhoto
    {
        $this->actingAs($this->user)->post(route('collection.photos.store', $this->lot), [
            'photos' => [UploadedFile::fake()->image('front.jpg')],
        ]);

        return $this->lot->photos()->sole();
    }
}
