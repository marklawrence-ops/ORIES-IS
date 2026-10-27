<?php

namespace App\Services;

use App\Jobs\BackupAttachmentToDrive;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stores Means of Verification on the institution's own disk first (ORIES_MOV_DISK: a local
 * folder on a university server, or an object-storage bucket on a cloud host), then queues a
 * Google Drive backup.
 */
class MovStorage
{
    public function __construct(private GoogleDrive $drive) {}

    public function storeUpload(UploadedFile $file, Project $project, ?ProjectUpdate $update, User $user): Attachment
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $path = $file->storeAs($this->directory($project), Str::uuid().'.'.$extension, $this->disk());

        return $this->register($project, $update, $user, $path, $file->getClientOriginalName(), $file->getMimeType(), 'upload');
    }

    public function storeContents(string $contents, string $name, ?string $mimeType, Project $project, ?ProjectUpdate $update, User $user, string $source): Attachment
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: 'bin');
        $path = $this->directory($project).'/'.Str::uuid().'.'.$extension;
        Storage::disk($this->disk())->put($path, $contents);

        return $this->register($project, $update, $user, $path, $name, $mimeType, $source);
    }

    public function exists(Attachment $attachment): bool
    {
        return Storage::disk($this->disk())->exists($attachment->path);
    }

    public function contents(Attachment $attachment): string
    {
        return (string) Storage::disk($this->disk())->get($attachment->path);
    }

    /**
     * Streams the file from whichever disk holds it (local folder or a cloud bucket).
     */
    public function response(Attachment $attachment, string $disposition): StreamedResponse
    {
        return Storage::disk($this->disk())->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream'],
            $disposition,
        );
    }

    public function disk(): string
    {
        return config('ories.mov_disk', 'local');
    }

    private function register(Project $project, ?ProjectUpdate $update, User $user, string $path, string $name, ?string $mimeType, string $source): Attachment
    {
        $disk = Storage::disk($this->disk());

        $attachment = Attachment::create([
            'project_id' => $project->id,
            'project_update_id' => $update?->id,
            'uploaded_by' => $user->id,
            'original_name' => $name,
            'path' => $path,
            'mime_type' => $mimeType,
            'size' => $disk->size($path),
            'checksum' => hash('sha256', $disk->get($path)),
            'source' => $source,
            'drive_status' => $this->drive->backupEnabled() ? 'pending' : 'disabled',
        ]);

        if ($attachment->drive_status === 'pending') {
            BackupAttachmentToDrive::dispatch($attachment->id)->afterCommit();
        }

        return $attachment;
    }

    private function directory(Project $project): string
    {
        return 'movs/'.$project->office.'/'.$project->id;
    }
}
