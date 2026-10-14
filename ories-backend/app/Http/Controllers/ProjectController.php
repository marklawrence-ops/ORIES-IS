<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\Project;
use App\Models\ProjectOutcome;
use App\Models\ProjectTag;
use App\Models\ProjectUpdate;
use App\Models\Unit;
use App\Models\User;
use App\Services\DuplicateTitleChecker;
use App\Services\MovStorage;
use App\Services\Notifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function __construct(
        private DuplicateTitleChecker $checker,
        private MovStorage $storage,
        private Notifier $notifier,
    ) {}

    /**
     * Search and filter records. Also serves the dashboard drill-downs.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validate([
            'q' => 'nullable|string|max:255',
            'office' => ['nullable', Rule::in(User::OFFICES)],
            'stage' => ['nullable', Rule::in(Project::STAGES)],
            'verification_status' => 'nullable|in:pending,verified,returned',
            'campus_id' => 'nullable|integer',
            'unit_id' => 'nullable|integer',
            'outcome' => ['nullable', Rule::in(ProjectOutcome::TYPES)],
            'indexing' => ['nullable', Rule::in(ProjectOutcome::INDEXING)],
            'year' => 'nullable|integer|min:1990|max:2100',
            'member_id' => 'nullable|integer',
            'scope' => 'nullable|in:mine,responsibility,all',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = Project::query()
            ->visibleTo($user)
            ->with(['lead:id,name', 'campus:id,name', 'unit:id,name,code'])
            ->withCount('outcomes');

        match ($filters['scope'] ?? 'all') {
            'mine' => $query->involving($user),
            'responsibility' => $query->withinScopeOf($user),
            default => null,
        };

        $query
            ->when($filters['q'] ?? null, function (Builder $q, string $term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(keywords) LIKE ?', [$like])
                    ->orWhereHas('members', fn (Builder $m) => $m->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('tags', fn (Builder $t) => $t->whereRaw('LOWER(label) LIKE ?', [$like])));
            })
            ->when($filters['office'] ?? null, fn (Builder $q, $v) => $q->where('office', $v))
            ->when($filters['stage'] ?? null, fn (Builder $q, $v) => $q->where('stage', $v))
            ->when($filters['verification_status'] ?? null, fn (Builder $q, $v) => $q->where('verification_status', $v))
            ->when($filters['campus_id'] ?? null, fn (Builder $q, $v) => $q->where('campus_id', $v))
            ->when($filters['unit_id'] ?? null, fn (Builder $q, $v) => $q->where('unit_id', $v))
            ->when($filters['member_id'] ?? null, fn (Builder $q, $v) => $q->involving(User::findOrFail($v)))
            ->when($filters['outcome'] ?? null, fn (Builder $q, $v) => $q->whereHas('outcomes', fn (Builder $o) => $o
                ->where('type', $v)
                ->when($filters['indexing'] ?? null, fn (Builder $o, $i) => $o->where('indexing', $i))))
            ->when(! isset($filters['outcome']) && isset($filters['indexing']), fn (Builder $q) => $q
                ->whereHas('outcomes', fn (Builder $o) => $o->where('indexing', $filters['indexing'])))
            ->when($filters['year'] ?? null, fn (Builder $q, $y) => $q->where(fn (Builder $w) => $w
                ->whereYear('proposed_at', $y)
                ->orWhereYear('start_date', $y)
                ->orWhereYear('completed_at', $y)));

        return response()->json(
            $query->orderByDesc('updated_at')->paginate($filters['per_page'] ?? 15)->withQueryString()
        );
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        abort_unless(Project::visibleTo($user)->whereKey($project->id)->exists(), 404);

        $project->load([
            'lead:id,name,email,position',
            'campus:id,name',
            'unit:id,name,code',
            'members.user:id,name,position',
            'tags',
            'outcomes',
            'certificates' => fn ($q) => $q->with('recipient:id,name')->latest(),
        ]);

        $inspect = $project->canBeInspectedBy($user);
        $canVerify = $user->canVerify($project->office);

        $updates = $project->updates()
            ->with(['submitter:id,name', 'reviewer:id,name', 'attachments.uploader:id,name'])
            ->latest()
            ->get()
            ->map(function (ProjectUpdate $update) use ($user, $project, $inspect, $canVerify) {
                // Unverified files are visible only to the people involved and the verifiers
                $filesVisible = $update->status === 'verified' ? $inspect : ($project->isInvolved($user) || $canVerify);

                return [
                    ...$update->toArray(),
                    'summary' => $update->summary(),
                    'attachments' => $filesVisible ? $update->attachments : [],
                    'attachments_hidden' => ! $filesVisible && $update->attachments->isNotEmpty(),
                ];
            });

        $imported = $inspect
            ? $project->attachments()->whereNull('project_update_id')->with('uploader:id,name')->get()
            : collect();

        return response()->json([
            'project' => $project,
            'updates' => $updates,
            'imported_attachments' => $imported,
            'activity' => $inspect ? $project->activity()->with('user:id,name')->latest()->get() : [],
            'permissions' => [
                'is_involved' => $project->isInvolved($user),
                'can_inspect' => $inspect,
                'can_verify' => $canVerify,
                'can_submit_update' => $project->isInvolved($user)
                    && $project->verification_status === 'verified'
                    && ! $project->hasPendingUpdate(),
                'can_resubmit' => $project->lead_id === $user->id && $project->verification_status === 'returned',
                'can_request_certificate' => $project->isInvolved($user) && $project->verification_status === 'verified',
                'next_stages' => Project::STAGE_TRANSITIONS[$project->stage] ?? [],
            ],
        ]);
    }

    /**
     * Real-time duplicate warning while a proposal title is being typed.
     */
    public function checkTitle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'ignore_id' => 'nullable|integer',
        ]);

        return response()->json([
            'threshold' => $this->checker->threshold(),
            'matches' => $this->checker->findMatches($data['title'], $data['ignore_id'] ?? null),
        ]);
    }

    /**
     * A faculty member submits a new proposal with its MOVs. It stays pending until verified.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canSubmit(), 403, 'Only faculty and heads submit records.');

        $data = $this->validateProject($request, requireFiles: true);

        $project = DB::transaction(function () use ($data, $user, $request) {
            $project = Project::create([
                ...collect($data)->only($this->projectFields())->all(),
                'lead_id' => $user->id,
                'campus_id' => $user->campus_id,
                'unit_id' => $user->unit_id,
                'stage' => 'proposal',
                'verification_status' => 'pending',
                'proposed_at' => $data['proposed_at'] ?? now()->toDateString(),
            ]);

            $project->members()->create(['user_id' => $user->id, 'name' => $user->name, 'role' => 'lead']);
            $this->syncMembers($project, $data['members'] ?? []);
            $this->syncTags($project, $data['tags'] ?? []);

            $update = $this->openUpdate($project, $user, 'proposal', remarks: $data['remarks'] ?? null);
            $this->storeFiles($request, $project, $update, $user);

            ActivityLog::record($project, $user, 'submitted', 'Submitted the proposal for verification.');

            return $project;
        });

        $this->notifyVerifiers($project, 'New proposal awaiting verification', $project->title);

        return response()->json(['message' => 'Proposal submitted for verification.', 'project' => $project], 201);
    }

    /**
     * The lead corrects a returned proposal and resubmits it.
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        abort_unless($project->lead_id === $user->id, 403);
        abort_unless($project->verification_status === 'returned', 422, 'Only returned proposals can be edited.');

        $data = $this->validateProject($request, requireFiles: false);

        DB::transaction(function () use ($project, $data, $user, $request) {
            $status = Project::whereKey($project->id)->lockForUpdate()->value('verification_status');
            abort_unless($status === 'returned', 422, 'Only returned proposals can be edited.');

            $project->update([
                ...collect($data)->only($this->projectFields())->all(),
                'verification_status' => 'pending',
            ]);

            $project->members()->where('role', '!=', 'lead')->delete();
            $this->syncMembers($project, $data['members'] ?? []);
            $project->tags()->delete();
            $this->syncTags($project, $data['tags'] ?? []);

            $update = $this->openUpdate($project, $user, 'proposal', remarks: $data['remarks'] ?? null);
            $this->storeFiles($request, $project, $update, $user);

            ActivityLog::record($project, $user, 'resubmitted', 'Revised and resubmitted the proposal.');
        });

        $this->notifyVerifiers($project, 'Revised proposal awaiting verification', $project->title);

        return response()->json(['message' => 'Proposal resubmitted.', 'project' => $project->fresh()]);
    }

    /**
     * Submit a stage change or an outcome (publication, commercialization, policy, technology transfer)
     * with its Means of Verification.
     */
    public function submitUpdate(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        abort_unless($project->isInvolved($user), 403);
        abort_unless($project->verification_status === 'verified', 422, 'The proposal must be verified first.');
        abort_if($project->hasPendingUpdate(), 422, 'This record already has an update awaiting verification.');

        $data = $request->validate([
            'type' => 'required|in:stage,outcome',
            'to_stage' => ['required_if:type,stage', 'nullable', Rule::in(Project::STAGE_TRANSITIONS[$project->stage] ?? [])],
            'date' => 'nullable|date',
            'outcome.type' => ['required_if:type,outcome', 'nullable', Rule::in(ProjectOutcome::TYPES)],
            'outcome.title' => 'required_if:type,outcome|nullable|string|max:255',
            'outcome.venue' => 'nullable|string|max:255',
            'outcome.indexing' => ['nullable', Rule::in(ProjectOutcome::INDEXING)],
            'outcome.date' => 'nullable|date',
            'outcome.url' => 'nullable|url|max:255',
            'outcome.citations' => 'nullable|integer|min:0',
            'outcome.notes' => 'nullable|string|max:2000',
            'remarks' => 'nullable|string|max:2000',
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'file|max:'.config('ories.max_upload_kb').'|mimes:'.config('ories.allowed_mimes'),
        ], [
            'files.required' => 'Attach at least one Means of Verification.',
            'to_stage.in' => 'That stage change is not allowed from the current stage.',
        ]);

        $update = DB::transaction(function () use ($project, $user, $data, $request) {
            // Locking the record serializes submissions so a double click cannot queue two updates
            Project::lockForUpdate()->findOrFail($project->id);
            abort_if($project->hasPendingUpdate(), 422, 'This record already has an update awaiting verification.');

            $update = $data['type'] === 'stage'
                ? $this->openUpdate($project, $user, 'stage', $project->stage, $data['to_stage'], ['date' => $data['date'] ?? now()->toDateString()], $data['remarks'] ?? null)
                : $this->openUpdate($project, $user, 'outcome', data: $data['outcome'], remarks: $data['remarks'] ?? null);

            $this->storeFiles($request, $project, $update, $user);
            ActivityLog::record($project, $user, 'update_submitted', $update->summary().' submitted for verification.');

            return $update;
        });

        $this->notifyVerifiers($project, 'Update awaiting verification', $update->summary().' — '.$project->title);

        return response()->json(['message' => 'Update submitted for verification.', 'update' => $update], 201);
    }

    private function validateProject(Request $request, bool $requireFiles): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'office' => ['required', Rule::in(User::OFFICES)],
            'abstract' => 'nullable|string|max:5000',
            'keywords' => 'nullable|string|max:255',
            'funding_source' => 'nullable|string|max:255',
            'budget' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'proposed_at' => 'nullable|date',
            'remarks' => 'nullable|string|max:2000',
            'members' => 'nullable|array|max:30',
            'members.*.user_id' => 'nullable|integer|exists:users,id',
            'members.*.name' => 'required_without:members.*.user_id|nullable|string|max:255',
            'tags' => 'nullable|array|max:30',
            'tags.*.type' => ['required', Rule::in(ProjectTag::TYPES)],
            'tags.*.unit_id' => 'nullable|integer|exists:units,id',
            'tags.*.campus_id' => 'nullable|integer|exists:campuses,id',
            'tags.*.label' => 'nullable|string|max:255',
            'files' => [$requireFiles ? 'required' : 'nullable', 'array', 'max:10'],
            'files.*' => 'file|max:'.config('ories.max_upload_kb').'|mimes:'.config('ories.allowed_mimes'),
        ], [
            'files.required' => 'Attach the proposal document as the Means of Verification.',
        ]);
    }

    private function projectFields(): array
    {
        return ['title', 'office', 'abstract', 'keywords', 'funding_source', 'budget', 'start_date', 'end_date', 'proposed_at'];
    }

    private function openUpdate(Project $project, User $user, string $type, ?string $from = null, ?string $to = null, ?array $data = null, ?string $remarks = null): ProjectUpdate
    {
        return $project->updates()->create([
            'submitted_by' => $user->id,
            'type' => $type,
            'from_stage' => $from,
            'to_stage' => $to,
            'data' => $data,
            'remarks' => $remarks,
            'status' => 'pending',
            'duplicate_matches' => $type === 'proposal' ? $this->checker->findMatches($project->title, $project->id) : null,
        ]);
    }

    private function storeFiles(Request $request, Project $project, ProjectUpdate $update, User $user): void
    {
        foreach ($request->file('files', []) as $file) {
            $this->storage->storeUpload($file, $project, $update, $user);
        }
    }

    private function syncMembers(Project $project, array $members): void
    {
        foreach ($members as $member) {
            $account = isset($member['user_id']) ? User::find($member['user_id']) : null;
            if ($account && $account->id === $project->lead_id) {
                continue;
            }
            $project->members()->create([
                'user_id' => $account?->id,
                'name' => $account?->name ?? $member['name'],
                'role' => 'member',
            ]);
        }
    }

    private function syncTags(Project $project, array $tags): void
    {
        foreach ($tags as $tag) {
            $label = match ($tag['type']) {
                'unit' => isset($tag['unit_id']) ? Unit::find($tag['unit_id'])?->name : null,
                'campus' => isset($tag['campus_id']) ? Campus::find($tag['campus_id'])?->name : null,
                default => null,
            } ?? ($tag['label'] ?? null);

            if (! $label) {
                continue;
            }

            $project->tags()->create([
                'type' => $tag['type'],
                'unit_id' => $tag['type'] === 'unit' ? ($tag['unit_id'] ?? null) : null,
                'campus_id' => $tag['type'] === 'campus' ? ($tag['campus_id'] ?? null) : null,
                'label' => $label,
            ]);
        }
    }

    private function notifyVerifiers(Project $project, string $title, string $body): void
    {
        $this->notifier->send($this->notifier->verifiersFor($project->office), $title, $body, '/verification');
    }
}
