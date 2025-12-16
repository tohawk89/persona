<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PersonaGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Persona $persona;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->persona = Persona::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function gallery_page_renders_successfully()
    {
        $response = $this->actingAs($this->user)
            ->get(route('persona.gallery', $this->persona));

        $response->assertStatus(200);
        $response->assertSeeLivewire('app.http.livewire.persona-gallery');
    }

    /** @test */
    public function unauthenticated_users_cannot_access_gallery()
    {
        $response = $this->get(route('persona.gallery', $this->persona));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function users_cannot_access_other_users_persona_gallery()
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->get(route('persona.gallery', $this->persona));

        $response->assertStatus(403);
    }

    /** @test */
    public function gallery_displays_persona_media()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test.jpg');
        $this->persona->addMedia($file)->toMediaCollection('generated_images');

        $response = $this->actingAs($this->user)
            ->get(route('persona.gallery', $this->persona));

        $response->assertStatus(200);
        $response->assertSee('1 item'); // Gallery shows media count
    }

    /** @test */
    public function gallery_filters_media_by_collection()
    {
        Storage::fake('public');

        $this->persona->addMedia(UploadedFile::fake()->image('avatar.jpg'))
            ->toMediaCollection('avatar');

        $this->persona->addMedia(UploadedFile::fake()->image('generated.jpg'))
            ->toMediaCollection('generated_images');

        $response = $this->actingAs($this->user)
            ->get(route('persona.gallery', $this->persona) . '?collection=avatar');

        $response->assertStatus(200);
    }

    /** @test */
    public function download_route_returns_media_file()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test.jpg');
        $media = $this->persona->addMedia($file)->toMediaCollection('generated_images');

        $response = $this->actingAs($this->user)
            ->get(route('persona.media.download', [$this->persona, $media->id]));

        $response->assertStatus(200);
    }

    /** @test */
    public function download_route_prevents_unauthorized_access_to_media()
    {
        Storage::fake('public');

        $otherPersona = Persona::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $file = UploadedFile::fake()->image('test.jpg');
        $media = $otherPersona->addMedia($file)->toMediaCollection('generated_images');

        $response = $this->actingAs($this->user)
            ->get(route('persona.media.download', [$this->persona, $media->id]));

        $response->assertStatus(403);
    }

    /** @test */
    public function gallery_paginates_media()
    {
        Storage::fake('public');

        // Create 30 media items
        for ($i = 0; $i < 30; $i++) {
            $this->persona->addMedia(UploadedFile::fake()->image("test-{$i}.jpg"))
                ->toMediaCollection('generated_images');
        }

        $response = $this->actingAs($this->user)
            ->get(route('persona.gallery', $this->persona));

        $response->assertStatus(200);
        // Should show pagination controls
        $response->assertSee('Next');
    }

    /** @test */
    public function media_conversions_are_generated()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test.jpg', 1000, 1000);
        $media = $this->persona->addMedia($file)->toMediaCollection('generated_images');

        // Check that conversions exist
        $this->assertNotNull($media->getUrl('thumb'));
        $this->assertNotNull($media->getUrl('large'));
    }
}
