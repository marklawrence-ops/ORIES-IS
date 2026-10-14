<?php

namespace Tests\Feature;

use App\Jobs\BackupAttachmentToDrive;
use App\Models\Attachment;
use App\Services\GoogleDrive;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DriveBackupTest extends TestCase
{
    public function test_uploads_are_backed_up_to_google_drive_when_enabled(): void
    {
        $drive = Mockery::mock(GoogleDrive::class);
        $drive->shouldReceive('backupEnabled')->andReturn(true);
        $drive->shouldReceive('upload')->once()->andReturn('drive-file-123');
        $this->app->instance(GoogleDrive::class, $drive);

        $this->submitProposal();

        $attachment = Attachment::firstOrFail();
        $this->assertSame('synced', $attachment->drive_status);
        $this->assertSame('drive-file-123', $attachment->drive_file_id);
    }

    public function test_retry_requeues_only_failed_or_stuck_backups(): void
    {
        $this->submitProposal();
        $this->submitProposal(overrides: ['title' => 'Second Proposal on Coastal Mangrove Mapping']);
        $this->submitProposal(overrides: ['title' => 'Third Proposal on Abaca Fiber Grading']);
        [$fresh, $failed, $stuck] = Attachment::orderBy('id')->get()->all();
        Attachment::whereKey($fresh->id)->update(['drive_status' => 'pending']);
        Attachment::whereKey($failed->id)->update(['drive_status' => 'failed']);
        Attachment::whereKey($stuck->id)->update(['drive_status' => 'pending', 'updated_at' => now()->subDays(2)]);

        $drive = Mockery::mock(GoogleDrive::class);
        $drive->shouldReceive('backupEnabled')->andReturn(true);
        $this->app->instance(GoogleDrive::class, $drive);
        Queue::fake();

        $this->artisan('ories:retry-drive-backups')->assertSuccessful();

        Queue::assertPushed(BackupAttachmentToDrive::class, 2);
        Queue::assertPushed(BackupAttachmentToDrive::class, fn ($job) => $job->attachmentId === $failed->id);
        Queue::assertPushed(BackupAttachmentToDrive::class, fn ($job) => $job->attachmentId === $stuck->id);
    }

    public function test_drive_outage_keeps_the_local_copy_usable(): void
    {
        $drive = Mockery::mock(GoogleDrive::class);
        $drive->shouldReceive('backupEnabled')->andReturn(true);
        $drive->shouldReceive('upload')->andThrow(new RuntimeException('Drive unavailable'));
        $this->app->instance(GoogleDrive::class, $drive);

        try {
            $this->submitProposal();
        } catch (RuntimeException) {
            // The sync queue rethrows job failures in tests; production uses the database queue
        }

        $attachment = Attachment::firstOrFail();
        $this->assertNotSame('synced', $attachment->drive_status);

        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->get("/api/attachments/{$attachment->id}")->assertOk();
    }
}
