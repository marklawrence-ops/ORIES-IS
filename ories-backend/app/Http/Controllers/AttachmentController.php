<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\MovStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(private MovStorage $storage) {}

    /**
     * Streams a Means of Verification from storage. Files of unverified updates are
     * restricted to the uploader's team and the verifiers.
     */
    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $user = $request->user();
        $project = $attachment->project;
        $update = $attachment->projectUpdate;

        $allowed = ($update && $update->status !== 'verified')
            ? $project->isInvolved($user) || $user->canVerify($project->office)
            : $project->canBeInspectedBy($user);

        abort_unless($allowed, 403);
        abort_unless($this->storage->exists($attachment), 404, 'The file is missing from storage.');

        return $this->storage->response($attachment, $request->boolean('download') ? 'attachment' : 'inline');
    }
}
