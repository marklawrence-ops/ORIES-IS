<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Project;
use App\Models\ProjectOutcome;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Role-scoped dashboard. Every figure carries the filters needed to drill down
 * into the matching records or people.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $scoped = fn () => Project::query()->verified()->withinScopeOf($user);
        $outcomes = fn () => ProjectOutcome::query()->whereHas('project', fn (Builder $q) => $q->verified()->withinScopeOf($user));

        $byStage = $scoped()->selectRaw('stage, COUNT(*) as total')->groupBy('stage')->pluck('total', 'stage');
        $byOffice = $scoped()->selectRaw('office, COUNT(*) as total')->groupBy('office')->pluck('total', 'office');

        $stats = [
            $this->stat('records', 'Verified records', $byStage->sum(), ['verification_status' => 'verified']),
            $this->stat('proposals', 'Proposals', (int) ($byStage['proposal'] ?? 0), ['stage' => 'proposal']),
            $this->stat('ongoing', 'Ongoing', (int) ($byStage['ongoing'] ?? 0), ['stage' => 'ongoing']),
            $this->stat('completed', 'Completed', (int) ($byStage['completed'] ?? 0), ['stage' => 'completed']),
            $this->stat('publications', 'Publications', $outcomes()->where('type', 'publication')->count(), ['outcome' => 'publication']),
            $this->stat('scopus', 'Scopus-indexed', $outcomes()->where('type', 'publication')->where('indexing', 'scopus')->count(), ['outcome' => 'publication', 'indexing' => 'scopus']),
            $this->stat('commercialization', 'Commercialized', $outcomes()->where('type', 'commercialization')->count(), ['outcome' => 'commercialization']),
            $this->stat('policy', 'Adopted as policy', $outcomes()->where('type', 'policy')->count(), ['outcome' => 'policy']),
            $this->stat('technology_transfer', 'Technology transfer', $outcomes()->where('type', 'technology_transfer')->count(), ['outcome' => 'technology_transfer']),
        ];

        $people = [];
        if (! $user->hasRole('faculty')) {
            foreach (['research' => 'Active researchers', 'extension' => 'Active extensionists', 'innovation' => 'Active innovators'] as $office => $label) {
                $people[] = [
                    'key' => 'active_'.$office,
                    'label' => $label,
                    'value' => $this->activePeople($user, $office)->count(),
                    'office' => $office,
                ];
            }
        }

        return response()->json([
            'role' => $user->role,
            'scope' => $this->scopeLabel($user),
            'stats' => $stats,
            'people' => $people,
            'by_office' => collect(User::OFFICES)->map(fn ($o) => ['office' => $o, 'total' => (int) ($byOffice[$o] ?? 0)]),
            'by_campus' => $scoped()
                ->join('campuses', 'campuses.id', '=', 'projects.campus_id')
                ->selectRaw('campuses.id as campus_id, campuses.name as campus, COUNT(*) as total')
                ->groupBy('campuses.id', 'campuses.name')
                ->orderBy('campuses.name')
                ->get(),
            'trend' => $this->trend($user),
            'tasks' => $this->tasks($user),
            'recent' => Project::query()->visibleTo($user)->withinScopeOf($user)
                ->with('lead:id,name')->latest('updated_at')->limit(6)
                ->get(['id', 'title', 'office', 'stage', 'verification_status', 'lead_id', 'updated_at']),
        ]);
    }

    /**
     * Drill-down list of personnel behind the "active" counts.
     */
    public function people(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->hasRole('faculty'), 403);

        $office = $request->validate(['office' => 'required|in:research,innovation,extension'])['office'];

        $people = $this->activePeople($user, $office)
            ->with(['unit:id,code,name', 'campus:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'position', 'unit_id', 'campus_id'])
            ->map(function (User $person) use ($user, $office) {
                $projects = Project::query()->verified()->withinScopeOf($user)->where('office', $office)->involving($person);

                return [
                    ...$person->toArray(),
                    'ongoing' => (clone $projects)->where('stage', 'ongoing')->count(),
                    'total' => $projects->count(),
                ];
            });

        return response()->json(['office' => $office, 'people' => $people]);
    }

    /**
     * Personnel with at least one verified ongoing record in the office, within the user's scope.
     */
    private function activePeople(User $user, string $office): Builder
    {
        $projectIds = Project::query()->verified()->withinScopeOf($user)
            ->where('office', $office)->where('stage', 'ongoing')->select('id');

        return User::query()->where(fn (Builder $q) => $q
            ->whereIn('id', Project::query()->whereIn('id', $projectIds)->select('lead_id'))
            ->orWhereIn('id', \App\Models\ProjectMember::query()->whereIn('project_id', $projectIds)->whereNotNull('user_id')->select('user_id')));
    }

    private function trend(User $user): array
    {
        $year = now()->year;
        $projects = Project::query()->verified()->withinScopeOf($user)->get(['proposed_at', 'completed_at']);

        return collect(range($year - 4, $year))->map(fn (int $y) => [
            'year' => $y,
            'proposals' => $projects->filter(fn ($p) => $p->proposed_at?->year === $y)->count(),
            'completed' => $projects->filter(fn ($p) => $p->completed_at?->year === $y)->count(),
        ])->all();
    }

    private function tasks(User $user): array
    {
        $tasks = [];

        if ($user->hasRole('staff', 'director')) {
            $tasks[] = [
                'label' => 'Updates awaiting verification',
                'value' => ProjectUpdate::where('status', 'pending')
                    ->whereHas('project', fn (Builder $q) => $user->office ? $q->where('office', $user->office) : $q)->count(),
                'link' => '/verification',
            ];
        }
        if ($user->hasRole('director')) {
            $tasks[] = [
                'label' => 'Certificate requests',
                'value' => Certificate::where('status', 'requested')
                    ->whereHas('project', fn (Builder $q) => $user->office ? $q->where('office', $user->office) : $q)->count(),
                'link' => '/certificates?status=requested',
            ];
        }
        if ($user->canManageUsers()) {
            $tasks[] = [
                'label' => 'Accounts awaiting approval',
                'value' => User::where('status', 'pending')->count(),
                'link' => '/admin/users?status=pending',
            ];
        }
        if ($user->canSubmit()) {
            $mine = Project::query()->involving($user);
            $tasks[] = [
                'label' => 'My updates awaiting verification',
                'value' => ProjectUpdate::where('status', 'pending')->whereIn('project_id', (clone $mine)->select('id'))->count(),
                'link' => '/projects?scope=mine',
            ];
            $tasks[] = [
                'label' => 'My records returned for correction',
                'value' => ProjectUpdate::where('status', 'returned')->where('submitted_by', $user->id)
                    ->where('reviewed_at', '>=', now()->subDays(30))->count(),
                'link' => '/projects?scope=mine',
            ];
        }

        return $tasks;
    }

    private function stat(string $key, string $label, int $value, array $filters): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value, 'filters' => $filters];
    }

    private function scopeLabel(User $user): string
    {
        return match ($user->role) {
            'faculty' => 'Your records',
            'head' => $user->unit?->name ?? 'Your college/unit',
            'staff', 'director' => $user->office ? ucfirst($user->office).' Office' : 'All ORIES offices',
            default => 'University-wide',
        };
    }
}
