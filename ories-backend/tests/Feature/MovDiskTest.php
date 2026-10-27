<?php

namespace Tests\Feature;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
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

    public function test_a_missing_file_returns_not_found(): void
    {
        $this->submitProposal();
        $attachment = Attachment::firstOrFail();
        Storage::disk('local')->delete($attachment->path);

        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->get("/api/attachments/{$attachment->id}")->assertNotFound();
    }
}
