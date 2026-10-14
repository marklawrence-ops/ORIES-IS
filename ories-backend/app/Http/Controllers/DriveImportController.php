<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Project;
use App\Services\GoogleDrive;
use App\Services\MovStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Transition aid: ORIES staff pull files that offices already keep in Google Drive into
 * the local repository and attach them to the matching verified record.
 */
class DriveImportController extends Controller
{
    public function __construct(private GoogleDrive $drive, private MovStorage $storage) {}

    public function status(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('staff'), 403);

        return response()->json([
            'configured' => $this->drive->isConfigured(),
            'backup_enabled' => $this->drive->backupEnabled(),
            'attachments' => Attachment::selectRaw('drive_status, COUNT(*) as total, SUM(size) as bytes')
                ->groupBy('drive_status')->get(),
            'local_bytes' => (int) Attachment::sum('size'),
        ]);
    }

    public function files(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('staff'), 403);

        if (! $this->drive->isConfigured()) {
            return response()->json(['message' => 'Google Drive is not configured on this server.'], 503);
        }

        try {
            $files = $this->drive->listImportFolder();
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Google Drive could not be reached. Local uploads are unaffected.'], 503);
        }

        $imported = Attachment::where('source', 'drive_import')->whereIn('drive_file_id', array_column($files, 'id'))
            ->pluck('project_id', 'drive_file_id');

        return response()->json([
            'files' => array_map(fn ($f) => [...$f, 'imported_to' => $imported[$f['id']] ?? null], $files),
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('staff'), 403);

        $data = $request->validate([
            'file_id' => 'required|string|max:255',
            'project_id' => 'required|integer|exists:projects,id',
        ]);

        $project = Project::findOrFail($data['project_id']);
        abort_unless($user->canVerify($project->office), 403);

        try {
            $file = $this->drive->download($data['file_id']);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'The file could not be downloaded from Google Drive.'], 503);
        }

        $attachment = $this->storage->storeContents($file['contents'], $file['name'], $file['mime_type'], $project, null, $user, 'drive_import');
        // The file already lives in Drive, so it does not need a second backup copy
        $attachment->update(['drive_file_id' => $data['file_id'], 'drive_status' => 'synced', 'drive_synced_at' => now()]);

        ActivityLog::record($project, $user, 'imported', 'Imported "'.$file['name'].'" from Google Drive.');

        return response()->json(['message' => 'File imported into local storage.', 'attachment' => $attachment], 201);
    }
}
