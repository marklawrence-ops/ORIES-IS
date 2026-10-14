<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Services\GoogleDrive;
use App\Services\MovStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupAttachmentToDrive implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $attachmentId) {}

    public function handle(GoogleDrive $drive, MovStorage $storage): void
    {
        $attachment = Attachment::find($this->attachmentId);
        if (! $attachment || $attachment->drive_status === 'synced') {
            return;
        }

        if (! $drive->backupEnabled()) {
            $attachment->update(['drive_status' => 'disabled']);

            return;
        }

        $fileId = $drive->upload(
            $storage->absolutePath($attachment),
            $attachment->project_id.'-'.$attachment->id.'-'.$attachment->original_name,
            $attachment->mime_type,
        );

        $attachment->update([
            'drive_file_id' => $fileId,
            'drive_status' => 'synced',
            'drive_synced_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        // The local copy is still the primary record, so a failed backup is only logged and retried later
        Attachment::whereKey($this->attachmentId)->update(['drive_status' => 'failed']);
        Log::warning('Google Drive backup failed', ['attachment' => $this->attachmentId, 'error' => $e->getMessage()]);
    }
}
