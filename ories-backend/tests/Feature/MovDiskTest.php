<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class MovDiskTest extends TestCase
{
    public function test_movs_are_stored_and_served_from_the_configured_disk(): void
    {
        // On Laravel Cloud the MOVs live in an object-storage bucket attached as its own disk
        Storage::fake('movs');
        config(['ories.mov_disk' => 'movs']);

        $this->submitProposal();
        $attachment = Attachment::firstOrFail();

        Storage::disk('movs')->assertExists($attachment->path);
        Storage::disk('local')->assertMissing($attachment->path);

        $this->actingAsUser('faculty@evsu.edu.ph');
        $response = $this->get("/api/attachments/{$attachment->id}?download=1")->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('mov.pdf', $response->headers->get('Content-Disposition'));
        $this->assertSame(Storage::disk('movs')->get($attachment->path), $response->streamedContent());
    }

    public function test_an_unconfigured_disk_is_reported_clearly_and_saves_nothing(): void
    {
        // As on Laravel Cloud when ORIES_MOV_DISK does not match the bucket's disk name
        config(['ories.mov_disk' => 'movs']);
        Log::spy();
        $this->actingAsUser('faculty@evsu.edu.ph');

        $this->post('/api/projects', ['title' => 'Rainwater Harvesting Design', 'office' => 'research', 'files' => [$this->mov()]], ['Accept' => 'application/json'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The file could not be saved, so nothing was submitted. Please try again later; if it keeps failing, contact the ORIES technical staff.');

        $this->assertSame(0, Project::count(), 'The proposal is rolled back with its file.');
        Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'no disk with that name is configured'))->once();
    }

    public function test_a_refused_write_is_reported_clearly_and_saves_nothing(): void
    {
        // Cloud buckets do not throw on a failed write; they return false
        $disk = Mockery::mock(Storage::fake('movs'))->makePartial();
        $disk->shouldReceive('putFileAs')->andReturn(false);
        Storage::set('movs', $disk);
        config(['ories.mov_disk' => 'movs']);
        Log::spy();
        $this->actingAsUser('faculty@evsu.edu.ph');

        $this->post('/api/projects', ['title' => 'Rainwater Harvesting Design', 'office' => 'research', 'files' => [$this->mov()]], ['Accept' => 'application/json'])
            ->assertStatus(503);

        $this->assertSame(0, Project::count());
        $this->assertSame(0, Attachment::count());
        Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'the disk refused the upload'))->once();
    }

    public function test_a_missing_file_returns_not_found(): void
    {
        $this->submitProposal();
        $attachment = Attachment::firstOrFail();
        Storage::disk('local')->delete($attachment->path);

        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->get("/api/attachments/{$attachment->id}")->assertNotFound();
    }
}
