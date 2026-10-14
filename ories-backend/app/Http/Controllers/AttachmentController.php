<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\MovStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    public function __construct(private MovStorage $storage) {}

    /**
     * Streams a Means of Verification from local storage. Files of unverified updates are
     * restricted to the uploader's team and the verifiers.
     */
    public function download(Request $request, Attachment $attachment): BinaryFileResponse
    {
        $user = $request->user();
        $project = $attachment->project;
        $update = $attachment->projectUpdate;

        $allowed = ($update && $update->status !== 'verified')
            ? $project->isInvolved($user) || $user->canVerify($project->office)
            : $project->canBeInspectedBy($user);

        abort_unless($allowed, 403);

        $path = $this->storage->absolutePath($attachment);
        abort_unless(is_file($path), 404, 'The file is missing from local storage.');

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response()->file($path, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => $disposition.'; filename="'.addslashes($attachment->original_name).'"',
        ]);
    }
}
