<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Certificate;
use App\Models\Project;
use App\Models\ProjectOutcome;
use App\Models\ProjectTag;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Services\DuplicateTitleChecker;
use App\Services\MovStorage;
use App\Services\Notifier;
use App\Support\Like;
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
            'activity' => 'nullable|in:awaiting,returned',
            'deadline' => 'nullable|in:overdue,ending_soon',
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

        match ($filters['activity'] ?? null) {
            'awaiting' => $query->awaitingVerification(),
            'returned' => $query->returnedForCorrection(),
            default => null,
        };

        match ($filters['deadline'] ?? null) {
            'overdue' => $query->pastEndDate(),
            'ending_soon' => $query->endingSoon(),
            default => null,
        };

        $query
            ->when($filters['q'] ?? null, function (Builder $q, string $term) {
                $like = Like::pattern($term);
                $q->where(fn (Builder $w) => $w
                    ->whereRaw(Like::sql('title'), [$like])
                    ->orWhereRaw(Like::sql('keywords'), [$like])
                    ->orWhereHas('members', fn (Builder $m) => $m->whereRaw(Like::sql('name'), [$like]))
                    ->orWhereHas('tags', fn (Builder $t) => $t->whereRaw(Like::sql('label'), [$like])));
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
            $query->orderByDesc('updated_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 15)->withQueryString()
                ->through(fn (Project $p) => [...$p->toArray(), 'deadline' => $p->deadline()])
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
            'certificates' => fn ($q) => $q->with('recipient:id,name')->latest()->orderByDesc('id'),
        ]);

        $inspect = $project->canBeInspectedBy($user);
        $canVerify = $user->canVerify($project->office);

        $updates = $project->updates()
            ->with(['submitter:id,name', 'reviewer:id,name', 'attachments.uploader:id,name'])
            ->latest()->orderByDesc('id')
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
            'project' => [...$project->toArray(), 'deadline' => $project->deadline()],
            'updates' => $updates,
            'imported_attachments' => $imported,
            'activity' => $inspect ? $project->activity()->with('user:id,name')->latest()->orderByDesc('id')->get() : [],
            'permissions' => [
                'is_involved' => $project->isInvolved($user),
                'can_inspect' => $inspect,
                'can_verify' => $canVerify,
                'can_submit_update' => $project->isInvolved($user)
                    && $project->verification_status === 'verified'
                    && ! $project->hasPendingUpdate(),
                'can_change_details' => $project->lead_id === $user->id
                    && $project->verification_status === 'verified'
                    && ! $project->hasPendingUpdate(),
                'can_withdraw' => $project->updates()->where('status', 'pending')->where('submitted_by', $user->id)->exists(),
                'can_resubmit' =>$project->lead_id === $user->id && $project->verification_status === 'returned',
                'can_request_certificate' => $canRequest = $project->isInvolved($user) && $project->verification_status === 'verified',
                // Each certificate type with the reason it can't be requested yet (null = available)
                'certificate_types' => $canRequest ? Certificate::availabilityFor($project, $user) : (object) [],
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
            $project->replaceMembers($project->normalizeMembers($data['members'] ?? []));
            $project->replaceTags(Project::normalizeTags($data['tags'] ?? []));

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

        // A withdrawn proposal was taken back before anyone checked its files, so it needs a new one;
        // after a return, the verifier may only have asked for corrected details
        $withdrawn = $project->updates()->where('type', 'proposal')->latest('id')->value('status') === 'withdrawn';
        $data = $this->validateProject($request, requireFiles: $withdrawn);

        DB::transaction(function () use ($project, $data, $user, $request) {
            $status = Project::whereKey($project->id)->lockForUpdate()->value('verification_status');
            abort_unless($status === 'returned', 422, 'Only returned proposals can be edited.');

            $project->update([
                ...collect($data)->only($this->projectFields())->all(),
                'verification_status' => 'pending',
            ]);

            $project->replaceMembers($project->normalizeMembers($data['members'] ?? []));
            $project->replaceTags(Project::normalizeTags($data['tags'] ?? []));

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

    /**
     * The lead proposes changes to a verified record's details, members, or tags (e.g., an approved
     * extension or a change of team). Only the changed values are kept, as before/after pairs; the
     * record itself stays as it is until a verifier applies them.
     */
    public function submitDetails(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        abort_unless($project->lead_id === $user->id, 403, 'Only the lead can change the record details.');
        abort_unless($project->verification_status === 'verified', 422, 'The proposal must be verified first.');
        abort_if($project->hasPendingUpdate(), 422, 'This record already has an update awaiting verification.');

        $data = $request->validate([
            ...collect($this->projectRules())->except(['office', 'proposed_at', 'remarks'])->all(),
            'remarks' => 'required|string|max:2000',
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'file|max:'.config('ories.max_upload_kb').'|mimes:'.config('ories.allowed_mimes'),
        ], [
            'remarks.required' => 'Explain the reason for the change.',
            'files.required' => 'Attach the approval or memo for this change as the Means of Verification.',
        ]);

        $changes = $this->detailChanges($project, $data);
        abort_if($changes === [], 422, 'Nothing was changed.');

        $update = DB::transaction(function () use ($project, $user, $data, $changes, $request) {
            Project::lockForUpdate()->findOrFail($project->id);
            abort_if($project->hasPendingUpdate(), 422, 'This record already has an update awaiting verification.');

            $update = $this->openUpdate($project, $user, 'details', data: $changes, remarks: $data['remarks']);
            if (isset($changes['fields']['title'])) {
                $update->update(['duplicate_matches' => $this->checker->findMatches($changes['fields']['title']['to'], $project->id)]);
            }
            $this->storeFiles($request, $project, $update, $user);
            ActivityLog::record($project, $user, 'update_submitted', $update->summary().' submitted for verification.');

            return $update;
        });

        $this->notifyVerifiers($project, 'Update awaiting verification', $update->summary().' — '.$project->title);

        return response()->json(['message' => 'Changes submitted for verification.', 'update' => $update], 201);
    }

    /**
     * The submitter takes back an update still awaiting verification (e.g., the wrong file was attached).
     * The record stays as it is; a withdrawn proposal goes back to the lead to correct and resubmit.
     */
    public function withdrawUpdate(Request $request, ProjectUpdate $update): JsonResponse
    {
        $user = $request->user();
        $project = $update->project;
        abort_unless($update->submitted_by === $user->id, 403, 'Only the person who submitted this update can withdraw it.');
        abort_unless($update->status === 'pending', 422, 'Only an update awaiting verification can be withdrawn.');

        DB::transaction(function () use ($update, $project, $user) {
            // Shares the verifiers' row lock, so a withdrawal and a review cannot both go through
            $status = ProjectUpdate::whereKey($update->id)->lockForUpdate()->value('status');
            abort_unless($status === 'pending', 422, 'Only an update awaiting verification can be withdrawn.');

            $update->update(['status' => 'withdrawn']);
            if ($update->type === 'proposal') {
                $project->update(['verification_status' => 'returned']);
            }

            ActivityLog::record($project, $user, 'withdrawn', $update->summary().' withdrawn by the submitter.');
        });

        return response()->json(['message' => 'Update withdrawn.']);
    }

    /**
     * Compares submitted details with the verified record.
     *
     * @return array{fields?: array<string, array{from: mixed, to: mixed}>, members?: array{from: array, to: array}, tags?: array{from: array, to: array}}
     */
    private function detailChanges(Project $project, array $data): array
    {
        $changes = [];
        $blank = fn ($v) => $v === null || $v === '' ? null : $v;

        foreach (['title', 'abstract', 'keywords', 'funding_source', 'budget', 'start_date', 'end_date'] as $field) {
            $from = $blank(match ($field) {
                'start_date', 'end_date' => $project->{$field}?->toDateString(),
                default => $project->{$field},
            });
            $to = $blank(is_string($data[$field] ?? null) ? trim($data[$field]) : ($data[$field] ?? null));
            $same = $field === 'budget' ? ($from === null) === ($to === null) && (float) $from === (float) $to : $from === $to;
            if (! $same) {
                $changes['fields'][$field] = ['from' => $from, 'to' => $to];
            }
        }

        $memberKey = fn (array $m) => $m['user_id'] ?? 'name:'.mb_strtolower($m['name']);
        $currentMembers = $project->members()->where('role', '!=', 'lead')->orderBy('id')->get(['user_id', 'name'])
            ->map(fn ($m) => ['user_id' => $m->user_id, 'name' => $m->name])->all();
        $members = $project->normalizeMembers($data['members'] ?? []);
        if (collect($currentMembers)->map($memberKey)->sort()->values()->all() !== collect($members)->map($memberKey)->sort()->values()->all()) {
            $changes['members'] = ['from' => $currentMembers, 'to' => $members];
        }

        $tagKey = fn (array $t) => $t['type'].'|'.$t['label'];
        $currentTags = $project->tags()->orderBy('id')->get(['type', 'unit_id', 'campus_id', 'label'])->map->only(['type', 'unit_id', 'campus_id', 'label'])->all();
        $tags = Project::normalizeTags($data['tags'] ?? []);
        if (collect($currentTags)->map($tagKey)->sort()->values()->all() !== collect($tags)->map($tagKey)->sort()->values()->all()) {
            $changes['tags'] = ['from' => $currentTags, 'to' => $tags];
        }

        return $changes;
    }

    private function validateProject(Request $request, bool $requireFiles): array
    {
        return $request->validate([
            ...$this->projectRules(),
            'files' => [$requireFiles ? 'required' : 'nullable', 'array', 'max:10'],
            'files.*' => 'file|max:'.config('ories.max_upload_kb').'|mimes:'.config('ories.allowed_mimes'),
        ], [
            'files.required' => 'Attach the proposal document as the Means of Verification.',
        ]);
    }

    private function projectRules(): array
    {
        return [
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
        ];
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

    private function notifyVerifiers(Project $project, string $title, string $body): void
    {
        $this->notifier->send($this->notifier->verifiersFor($project->office), $title, $body, '/verification');
    }
}
