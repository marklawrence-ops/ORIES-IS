<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Verification Module: ORIES staff (and directors) check each submitted update and its
 * Means of Verification before it becomes part of the official record.
 */
class VerificationController extends Controller
{
    public function __construct(private Notifier $notifier) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('staff', 'director'), 403);

        $status = $request->validate(['status' => 'nullable|in:pending,verified,returned'])['status'] ?? 'pending';

        $updates = ProjectUpdate::query()
            ->where('status', $status)
            ->whereHas('project', fn ($q) => $user->office ? $q->where('office', $user->office) : $q)
            ->with(['project:id,title,office,stage,verification_status,campus_id,unit_id', 'project.campus:id,name', 'project.unit:id,name,code', 'submitter:id,name', 'reviewer:id,name'])
            ->withCount('attachments')
            ->orderBy($status === 'pending' ? 'created_at' : 'reviewed_at', $status === 'pending' ? 'asc' : 'desc')
            ->orderBy('id', $status === 'pending' ? 'asc' : 'desc')
            ->paginate(20);

        $cutoff = ProjectUpdate::waitingCutoff();
        $updates->getCollection()->transform(fn (ProjectUpdate $u) => [
            ...$u->toArray(),
            'summary' => $u->summary(),
            ...($status === 'pending' ? ['waiting_days' => $u->waitingDays(), 'waiting_too_long' => $u->created_at->lt($cutoff)] : []),
        ]);

        return response()->json([
            ...$updates->toArray(),
            'verify_within_days' => (int) config('ories.verify_within_days'),
            'waiting_too_long' => ProjectUpdate::inQueueOf($user)->waitingTooLong()->count(),
        ]);
    }

    public function verify(Request $request, ProjectUpdate $update): JsonResponse
    {
        $user = $request->user();
        $project = $update->project;
        abort_unless($user->canVerify($project->office), 403);
        abort_unless($update->status === 'pending', 422, 'This update is no longer awaiting verification.');

        $remarks = $request->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        DB::transaction(function () use ($update, $project, $user, $remarks) {
            $this->lockPending($update);

            match ($update->type) {
                'proposal' => $project->verification_status = 'verified',
                'stage' => $this->applyStage($update),
                'outcome' => $project->outcomes()->create(collect($update->data)->only([
                    'type', 'title', 'venue', 'indexing', 'date', 'url', 'citations', 'notes',
                ])->all()),
                'details' => $this->applyDetails($update),
            };

            $project->last_verified_at = now();
            $project->save();

            $update->update([
                'status' => 'verified',
                'reviewed_by' => $user->id,
                'review_remarks' => $remarks,
                'reviewed_at' => now(),
            ]);

            ActivityLog::record($project, $user, 'verified', $update->summary().' verified.');
        });

        // The whole team hears about it, not only the person who submitted
        $this->notifier->send(
            $project->team()->push($update->submitter),
            'Update verified',
            $update->summary().' — '.$project->title,
            '/projects/'.$project->id,
        );

        return response()->json(['message' => 'Update verified and applied to the record.', 'next' => $this->next($user)]);
    }

    public function return(Request $request, ProjectUpdate $update): JsonResponse
    {
        $user = $request->user();
        $project = $update->project;
        abort_unless($user->canVerify($project->office), 403);
        abort_unless($update->status === 'pending', 422, 'This update is no longer awaiting verification.');

        $remarks = $request->validate(['remarks' => 'required|string|max:2000'], [
            'remarks.required' => 'Explain what needs to be corrected.',
        ])['remarks'];

        DB::transaction(function () use ($update, $project, $user, $remarks) {
            $this->lockPending($update);

            if ($update->type === 'proposal') {
                $project->update(['verification_status' => 'returned']);
            }

            $update->update([
                'status' => 'returned',
                'reviewed_by' => $user->id,
                'review_remarks' => $remarks,
                'reviewed_at' => now(),
            ]);

            ActivityLog::record($project, $user, 'returned', $update->summary().' returned: '.$remarks);
        });

        $this->notifier->send(
            $project->team()->push($update->submitter),
            'Update returned for correction',
            $update->summary().' — '.$project->title.' — '.$remarks,
            '/projects/'.$project->id,
        );

        return response()->json(['message' => 'Update returned to the submitter.', 'next' => $this->next($user)]);
    }

    /**
     * The oldest item still in the reviewer's queue, so the page can open it right away.
     */
    private function next(User $user): ?array
    {
        $queue = ProjectUpdate::inQueueOf($user);
        $next = (clone $queue)->orderBy('created_at')->orderBy('id')->first(['id', 'project_id']);

        return $next ? ['project_id' => $next->project_id, 'remaining' => $queue->count()] : null;
    }

    /**
     * Re-checks the status under a row lock so two reviewers acting at once (or a reviewer and a
     * withdrawing submitter) cannot both decide the update.
     */
    private function lockPending(ProjectUpdate $update): void
    {
        $status = ProjectUpdate::whereKey($update->id)->lockForUpdate()->value('status');
        abort_unless($status === 'pending', 422, 'This update is no longer awaiting verification.');
    }

    /**
     * Applies the "to" side of a change of details. Nothing else can change the record while the
     * update is pending (one pending update per record), so the "from" side still matches.
     */
    private function applyDetails(ProjectUpdate $update): void
    {
        $project = $update->project;

        foreach ($update->data['fields'] ?? [] as $field => $change) {
            $project->{$field} = $change['to'];
        }
        if (isset($update->data['members'])) {
            $project->replaceMembers($update->data['members']['to']);
        }
        if (isset($update->data['tags'])) {
            $project->replaceTags($update->data['tags']['to']);
        }
    }

    private function applyStage(ProjectUpdate $update): void
    {
        $project = $update->project;
        $date = $update->data['date'] ?? now()->toDateString();

        $project->stage = $update->to_stage;
        if ($update->to_stage === 'ongoing' && ! $project->start_date) {
            $project->start_date = $date;
        }
        if ($update->to_stage === 'completed') {
            $project->completed_at = $date;
        }
    }
}
