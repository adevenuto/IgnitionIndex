<?php

use App\Actions\Vehicles\StoreVehiclePhoto;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    // Always name the disk. phpunit.xml sets no FILESYSTEM_DISK and there is no
    // .env.testing, so a bare Storage::fake() would only work by coincidence of
    // whatever .env happens to say on this machine.
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('the primary photo is whichever sits at position zero', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();

    $second = VehiclePhoto::factory()->for($vehicle)->create(['position' => 1]);
    $first = VehiclePhoto::factory()->for($vehicle)->create(['position' => 0]);

    expect($vehicle->primaryPhoto()->first()->id)->toBe($first->id)
        ->and($first->isPrimary())->toBeTrue()
        ->and($second->isPrimary())->toBeFalse();
});

test('deleting a photo removes both of its files', function () {
    $photo = VehiclePhoto::factory()->for(
        Vehicle::factory()->for($this->user),
    )->create();

    Storage::disk('local')->assertExists($photo->path);
    Storage::disk('local')->assertExists($photo->thumbnail_path);

    $photo->delete();

    Storage::disk('local')->assertMissing($photo->path);
    Storage::disk('local')->assertMissing($photo->thumbnail_path);
});

test('deleting a vehicle removes every photo file', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();

    $photos = VehiclePhoto::factory()->count(3)->for($vehicle)
        ->sequence(['position' => 0], ['position' => 1], ['position' => 2])
        ->create();

    $paths = $photos->flatMap(fn (VehiclePhoto $p): array => [$p->path, $p->thumbnail_path]);

    $vehicle->delete();

    // The FK cascade alone would leave every one of these behind, because it
    // happens in the database and fires no model events.
    foreach ($paths as $path) {
        Storage::disk('local')->assertMissing($path);
    }

    expect(VehiclePhoto::count())->toBe(0);
});

test('a rolled back deletion keeps the files', function () {
    $photo = VehiclePhoto::factory()->for(
        Vehicle::factory()->for($this->user),
    )->create();

    rescue(function () use ($photo) {
        DB::transaction(function () use ($photo) {
            $photo->delete();

            throw new RuntimeException('something later in the request failed');
        });
    }, report: false);

    // afterCommit means the files survive a rollback that restores the row.
    Storage::disk('local')->assertExists($photo->path);
    expect(VehiclePhoto::whereKey($photo->id)->exists())->toBeTrue();
});

test('an upload is stored as webp, capped and thumbnailed', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();

    // Real dimensions matter: UploadedFile::fake()->image() defaults to 10x10,
    // and scaleDown never upscales, so a default fake would prove nothing.
    $photo = app(StoreVehiclePhoto::class)->handle(
        $vehicle,
        UploadedFile::fake()->image('front.jpg', 2400, 1200),
    );

    Storage::disk('local')->assertExists($photo->path);
    Storage::disk('local')->assertExists($photo->thumbnail_path);

    expect($photo->path)->toEndWith('.webp')
        ->and($photo->thumbnail_path)->toEndWith('-thumb.webp')
        ->and($photo->width)->toBe(1600)      // long edge capped
        ->and($photo->height)->toBe(800)      // aspect preserved
        ->and($photo->position)->toBe(0)
        ->and($photo->placeholder_color)->toStartWith('#');

    $mime = finfo_buffer(
        finfo_open(FILEINFO_MIME_TYPE),
        Storage::disk('local')->get($photo->path),
    );

    expect($mime)->toBe('image/webp');
});

test('a small photo is not upscaled', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();

    $photo = app(StoreVehiclePhoto::class)->handle(
        $vehicle,
        UploadedFile::fake()->image('small.jpg', 300, 200),
    );

    expect($photo->width)->toBe(300)->and($photo->height)->toBe(200);
});

test('a png upload is converted to webp', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();

    $photo = app(StoreVehiclePhoto::class)->handle(
        $vehicle,
        UploadedFile::fake()->image('side.png', 1200, 900),
    );

    $mime = finfo_buffer(
        finfo_open(FILEINFO_MIME_TYPE),
        Storage::disk('local')->get($photo->path),
    );

    expect($mime)->toBe('image/webp');
});

test('photo files are sharded under the vehicle directory', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();

    $photo = app(StoreVehiclePhoto::class)->handle(
        $vehicle,
        UploadedFile::fake()->image('a.jpg', 800, 600),
    );

    expect($photo->path)->toStartWith("vehicle-photos/{$vehicle->id}/");
});

test('the owner can load a photo, cached privately', function () {
    $photo = VehiclePhoto::factory()->for(
        Vehicle::factory()->for($this->user),
    )->create();

    $response = $this->get(route('vehicle-photos.show', $photo));

    $response->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/webp')
        ->and($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->headers->get('Cache-Control'))->not->toContain('public')
        ->and($response->headers->get('ETag'))->toContain($photo->ulid);
});

test('the thumbnail is served from its own route', function () {
    $photo = VehiclePhoto::factory()->for(
        Vehicle::factory()->for($this->user),
    )->create();

    $this->get(route('vehicle-photos.thumbnail', $photo))->assertOk();
});

test('another user cannot load a photo', function () {
    $photo = VehiclePhoto::factory()->for(Vehicle::factory())->create();

    $this->get(route('vehicle-photos.show', $photo))->assertForbidden();
    $this->get(route('vehicle-photos.thumbnail', $photo))->assertForbidden();
});

test('a guest cannot load a photo', function () {
    $photo = VehiclePhoto::factory()->for(
        Vehicle::factory()->for($this->user),
    )->create();

    auth()->logout();

    $this->get(route('vehicle-photos.show', $photo))->assertRedirect(route('login'));
});

test('an unknown photo is a 404', function () {
    $this->get('/vehicle-photos/'.Str::ulid())->assertNotFound();
});

test('a photo whose file is missing is a 404, not a 500', function () {
    $photo = VehiclePhoto::factory()->for(
        Vehicle::factory()->for($this->user),
    )->create();

    Storage::disk('local')->delete($photo->path);

    $this->get(route('vehicle-photos.show', $photo))->assertNotFound();
});

test('the photo bytes are streamed from the disk', function () {
    $photo = VehiclePhoto::factory()->for(
        Vehicle::factory()->for($this->user),
    )->create();

    expect($this->get(route('vehicle-photos.show', $photo))->streamedContent())
        ->toBe('fake-webp-bytes')
        ->and($this->get(route('vehicle-photos.thumbnail', $photo))->streamedContent())
        ->toBe('fake-webp-thumb-bytes');
});

test('a revalidation with a matching etag is a 304 with no body', function () {
    $photo = VehiclePhoto::factory()->for(
        Vehicle::factory()->for($this->user),
    )->create();

    $response = $this->get(
        route('vehicle-photos.show', $photo),
        ['If-None-Match' => '"'.$photo->ulid.'"'],
    );

    $response->assertStatus(304);

    expect($response->streamedContent())->toBe('');
});

test('a revalidation still requires ownership', function () {
    // The 304 short-circuit must not skip the owner check: a guessed ETag
    // would otherwise confirm that a photo exists.
    $photo = VehiclePhoto::factory()->for(Vehicle::factory())->create();

    $this->get(
        route('vehicle-photos.show', $photo),
        ['If-None-Match' => '"'.$photo->ulid.'"'],
    )->assertForbidden();
});

test('photos are served from whichever disk is configured', function () {
    // In production the disk is a Laravel Cloud bucket registered under its own
    // name, not "local". Nothing on the serving path may assume otherwise.
    Storage::fake('photos');
    config(['vehicles.photos.disk' => 'photos']);

    $photo = VehiclePhoto::factory()->for(
        Vehicle::factory()->for($this->user),
    )->create();

    Storage::disk('local')->assertMissing($photo->path);

    expect($this->get(route('vehicle-photos.show', $photo))->streamedContent())
        ->toBe('fake-webp-bytes');
});

test('photos are routed by ulid, not by sequential id', function () {
    $photo = VehiclePhoto::factory()->for(
        Vehicle::factory()->for($this->user),
    )->create();

    expect(route('vehicle-photos.show', $photo))->toContain($photo->ulid)
        ->and($photo->getRouteKeyName())->toBe('ulid');
});

test('a photo uploaded when adding a vehicle becomes the card image', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota', 'model' => 'RAV4', 'odometer' => 1000,
        'photos' => [UploadedFile::fake()->image('front.jpg', 2400, 1200)],
    ])->assertSessionHasNoErrors();

    $photo = VehiclePhoto::firstOrFail();

    expect($photo->position)->toBe(0)->and($photo->width)->toBe(1600);
    Storage::disk('local')->assertExists($photo->path);
});

test('a photo can be marked as the card image before it is uploaded', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota', 'model' => 'RAV4', 'odometer' => 1000,
        'photos' => [
            UploadedFile::fake()->image('a.jpg', 800, 600),
            UploadedFile::fake()->image('b.jpg', 900, 600),
        ],
        // The second uploaded file should end up first.
        'photo_order' => ['new:1', 'new:0'],
    ])->assertSessionHasNoErrors();

    $primary = Vehicle::firstOrFail()->primaryPhoto()->firstOrFail();

    expect($primary->width)->toBe(900);
});

test('a photo over ten megabytes is rejected', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota', 'model' => 'RAV4', 'odometer' => 1000,
        // size() only changes the REPORTED size, so this allocates nothing.
        'photos' => [UploadedFile::fake()->image('big.jpg')->size(11 * 1024)],
    ])->assertSessionHasErrors('photos.0');

    expect(VehiclePhoto::count())->toBe(0);
});

test('a non image file is rejected', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota', 'model' => 'RAV4', 'odometer' => 1000,
        'photos' => [UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf')],
    ])->assertSessionHasErrors('photos.0');

    expect(VehiclePhoto::count())->toBe(0);
});

test('a gif is rejected even though it is an image', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota', 'model' => 'RAV4', 'odometer' => 1000,
        'photos' => [UploadedFile::fake()->image('animation.gif', 400, 400)],
    ])->assertSessionHasErrors('photos.0');
});

test('an over sized image is rejected', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota', 'model' => 'RAV4', 'odometer' => 1000,
        // 6001 x 100, not 6001 x 6001 — no need to allocate 144 MB in a test.
        'photos' => [UploadedFile::fake()->image('huge.jpg', 6001, 100)],
    ])->assertSessionHasErrors('photos.0');
});

test('a seventh photo is rejected', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();

    VehiclePhoto::factory()->count(6)->for($vehicle)
        ->sequence(fn ($s) => ['position' => $s->index])->create();

    $this->put(route('vehicles.update', $vehicle), [
        'make' => 'Toyota', 'model' => 'RAV4',
        'photos' => [UploadedFile::fake()->image('seventh.jpg', 800, 600)],
    ])->assertSessionHasErrors('photos');

    expect($vehicle->photos()->count())->toBe(6);
});

test('removing one photo frees a slot for another', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();

    $photos = VehiclePhoto::factory()->count(6)->for($vehicle)
        ->sequence(fn ($s) => ['position' => $s->index])->create();

    $this->put(route('vehicles.update', $vehicle), [
        'make' => 'Toyota', 'model' => 'RAV4',
        'removed_photo_ids' => [$photos->first()->id],
        'photos' => [UploadedFile::fake()->image('replacement.jpg', 800, 600)],
    ])->assertSessionHasNoErrors();

    expect($vehicle->photos()->count())->toBe(6);
    Storage::disk('local')->assertMissing($photos->first()->path);
});

test('removing a middle photo renumbers without leaving a gap', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();

    $photos = VehiclePhoto::factory()->count(3)->for($vehicle)
        ->sequence(fn ($s) => ['position' => $s->index])->create();

    $this->put(route('vehicles.update', $vehicle), [
        'make' => 'Toyota', 'model' => 'RAV4',
        'removed_photo_ids' => [$photos[1]->id],
    ])->assertSessionHasNoErrors();

    expect($vehicle->photos()->pluck('position')->all())->toBe([0, 1]);
});

test('reordering promotes a different photo to the card image', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();

    $photos = VehiclePhoto::factory()->count(2)->for($vehicle)
        ->sequence(fn ($s) => ['position' => $s->index])->create();

    $this->put(route('vehicles.update', $vehicle), [
        'make' => 'Toyota', 'model' => 'RAV4',
        'photo_order' => ["existing:{$photos[1]->id}", "existing:{$photos[0]->id}"],
    ])->assertSessionHasNoErrors();

    expect($vehicle->primaryPhoto()->firstOrFail()->id)->toBe($photos[1]->id);
});

test('a user cannot remove another user photo by id', function () {
    $mine = Vehicle::factory()->for($this->user)->create();
    $theirs = VehiclePhoto::factory()->for(Vehicle::factory())->create();

    $this->put(route('vehicles.update', $mine), [
        'make' => 'Toyota', 'model' => 'RAV4',
        'removed_photo_ids' => [$theirs->id],
    ])->assertSessionHasErrors('removed_photo_ids.0');

    expect(VehiclePhoto::whereKey($theirs->id)->exists())->toBeTrue();
});

test('deleting a user removes every photo file they owned', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();

    $photos = VehiclePhoto::factory()->count(2)->for($vehicle)
        ->sequence(['position' => 0], ['position' => 1])->create();

    $paths = $photos->flatMap(fn (VehiclePhoto $p): array => [$p->path, $p->thumbnail_path]);

    $this->user->delete();

    // users -> vehicles is ALSO a database-level cascade, so without the user
    // observer none of the vehicle or photo observers would ever fire and every
    // file would outlive the account.
    foreach ($paths as $path) {
        Storage::disk('local')->assertMissing($path);
    }

    expect(VehiclePhoto::count())->toBe(0)
        ->and(Vehicle::count())->toBe(0);
});

test('the garage card gets the full photo as well as the thumbnail', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();
    $photo = VehiclePhoto::factory()->for($vehicle)->create(['position' => 0]);
    Vehicle::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('garage'))
        ->assertInertia(fn ($page) => $page
            ->where('vehicles.0.photo_thumb_url', route('vehicle-photos.thumbnail', $photo))
            ->where('vehicles.0.photo_url', route('vehicle-photos.show', $photo))
            ->where('vehicles.1.photo_url', null));
});
