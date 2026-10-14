<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Project;
use App\Models\ProjectOutcome;
use App\Models\Target;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Dynamic Reports Module. Every report draws only on verified records within the
 * requesting user's scope, filtered by office, campus, unit, and reporting period.
 */
class ReportService
{
    public const TYPES = [
        'summary' => 'Accomplishment Summary',
        'projects' => 'List of Research, Innovation, and Extension Records',
        'outcomes' => 'Publications and Utilization Outcomes',
        'certificates' => 'Issued Certificates',
        'ipcr' => 'Individual Accomplishments (IPCR Support)',
        'opcr' => 'Office Targets vs. Accomplishments (OPCR Support)',
        'ched' => 'Institutional Summary by Campus (CHED Support)',
    ];

    public const RANGES = ['this_year', 'last_3_years', 'last_5_years', 'last_10_years', 'custom'];

    private const UTILIZATION = ['commercialization', 'policy', 'technology_transfer'];

    /**
     * @param  array<string, mixed>  $filters
     * @return array{title:string, columns:array<int, array{key:string,label:string}>, rows:array<int, array<string, mixed>>, totals:?array<string, mixed>, meta:array<string, mixed>}
     */
    public function generate(User $user, string $type, array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $groupBy = $filters['group_by'] ?? 'year';

        $report = match ($type) {
            'summary' => $this->summary($user, $filters, $from, $to, $groupBy),
            'projects' => $this->projects($user, $filters, $from, $to),
            'outcomes' => $this->outcomes($user, $filters, $from, $to),
            'certificates' => $this->certificates($user, $filters, $from, $to),
            'ipcr' => $this->ipcr($user, $filters, $from, $to),
            'opcr' => $this->opcr($user, $filters, $from, $to),
            'ched' => $this->ched($user, $filters, $from, $to),
        };

        return [
            'title' => self::TYPES[$type],
            ...$report,
            'meta' => [
                'type' => $type,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'group_by' => $groupBy,
                'filters' => array_filter($filters, fn ($v) => $v !== null && $v !== ''),
                'scope' => $this->scopeLabel($user),
                'generated_by' => $user->name,
                'generated_at' => now()->toDateTimeString(),
            ],
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function period(array $filters): array
    {
        $now = CarbonImmutable::now();

        return match ($filters['range'] ?? 'this_year') {
            'last_3_years' => [$now->subYears(2)->startOfYear(), $now->endOfYear()],
            'last_5_years' => [$now->subYears(4)->startOfYear(), $now->endOfYear()],
            'last_10_years' => [$now->subYears(9)->startOfYear(), $now->endOfYear()],
            'custom' => [
                CarbonImmutable::parse($filters['from'] ?? $now->startOfYear())->startOfDay(),
                CarbonImmutable::parse($filters['to'] ?? $now)->endOfDay(),
            ],
            default => [$now->startOfYear(), $now->endOfYear()],
        };
    }

    private function summary(User $user, array $filters, CarbonImmutable $from, CarbonImmutable $to, string $groupBy): array
    {
        $projects = $this->baseProjects($user, $filters)->get(['id', 'proposed_at', 'start_date', 'completed_at']);
        $outcomes = $this->baseOutcomes($user, $filters)->whereBetween('date', [$from, $to])->get(['type', 'indexing', 'date']);
        $certificates = $this->baseCertificates($user, $filters)->whereBetween('issued_at', [$from, $to])->get(['issued_at']);

        $rows = [];
        foreach ($this->periods($from, $to, $groupBy) as $key => $label) {
            $rows[$key] = ['period' => $label, 'proposals' => 0, 'started' => 0, 'completed' => 0, 'publications' => 0, 'scopus' => 0, 'utilization' => 0, 'certificates' => 0];
        }

        $bump = function (?CarbonInterface $date, string $metric) use (&$rows, $from, $to, $groupBy) {
            if ($date && $date->betweenIncluded($from, $to)) {
                $rows[$this->periodKey($date, $groupBy)][$metric]++;
            }
        };

        foreach ($projects as $p) {
            $bump($p->proposed_at, 'proposals');
            $bump($p->start_date, 'started');
            $bump($p->completed_at, 'completed');
        }
        foreach ($outcomes as $o) {
            if ($o->type === 'publication') {
                $bump($o->date, 'publications');
                if ($o->indexing === 'scopus') {
                    $bump($o->date, 'scopus');
                }
            } elseif (in_array($o->type, self::UTILIZATION, true)) {
                $bump($o->date, 'utilization');
            }
        }
        foreach ($certificates as $c) {
            $bump($c->issued_at, 'certificates');
        }

        $rows = array_values($rows);

        return [
            'columns' => $this->columns([
                'period' => 'Period', 'proposals' => 'Proposals', 'started' => 'Started (Ongoing)', 'completed' => 'Completed',
                'publications' => 'Publications', 'scopus' => 'Scopus-Indexed', 'utilization' => 'Utilized (Comm./Policy/Tech. Transfer)', 'certificates' => 'Certificates Issued',
            ]),
            'rows' => $rows,
            'totals' => $this->sumRows($rows, 'period'),
        ];
    }

    private function projects(User $user, array $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->baseProjects($user, $filters)
            ->where(fn (Builder $q) => $q
                ->whereBetween('proposed_at', [$from, $to])
                ->orWhereBetween('start_date', [$from, $to])
                ->orWhereBetween('completed_at', [$from, $to])
                ->orWhere(fn (Builder $active) => $active->where('stage', 'ongoing')->where('start_date', '<=', $to)))
            ->with(['lead:id,name', 'members:id,project_id,name', 'campus:id,name', 'unit:id,code,name', 'outcomes:id,project_id,type'])
            ->orderBy('office')->orderBy('title')
            ->get()
            ->map(fn (Project $p) => [
                'title' => $p->title,
                'office' => ucfirst($p->office),
                'stage' => ucfirst($p->stage),
                'lead' => $p->lead?->name,
                'members' => $p->members->pluck('name')->implode('; '),
                'campus' => $p->campus?->name,
                'unit' => $p->unit?->code ?? $p->unit?->name,
                'proposed_at' => $p->proposed_at?->toDateString(),
                'start_date' => $p->start_date?->toDateString(),
                'completed_at' => $p->completed_at?->toDateString(),
                'funding_source' => $p->funding_source,
                'budget' => $p->budget !== null ? (float) $p->budget : null,
                'outcomes' => $p->outcomes->pluck('type')->map(fn ($t) => str_replace('_', ' ', $t))->unique()->implode(', '),
            ])
            ->all();

        return [
            'columns' => $this->columns([
                'title' => 'Title', 'office' => 'Office', 'stage' => 'Stage', 'lead' => 'Lead', 'members' => 'Members',
                'campus' => 'Campus', 'unit' => 'College/Unit', 'proposed_at' => 'Proposed', 'start_date' => 'Started',
                'completed_at' => 'Completed', 'funding_source' => 'Funding Source', 'budget' => 'Budget (PHP)', 'outcomes' => 'Outcomes',
            ]),
            'rows' => $rows,
            'totals' => ['title' => count($rows).' record(s)', 'budget' => array_sum(array_column($rows, 'budget'))],
        ];
    }

    private function outcomes(User $user, array $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->baseOutcomes($user, $filters)
            ->whereBetween('date', [$from, $to])
            ->when($filters['outcome'] ?? null, fn (Builder $q, $t) => $q->where('type', $t))
            ->when($filters['indexing'] ?? null, fn (Builder $q, $i) => $q->where('indexing', $i))
            ->with('project:id,title,office,lead_id', 'project.lead:id,name')
            ->orderByDesc('date')
            ->get()
            ->map(fn (ProjectOutcome $o) => [
                'date' => $o->date?->toDateString(),
                'type' => ucwords(str_replace('_', ' ', $o->type)),
                'title' => $o->title,
                'venue' => $o->venue,
                'indexing' => $o->indexing ? strtoupper($o->indexing) : null,
                'citations' => $o->citations,
                'project' => $o->project->title,
                'office' => ucfirst($o->project->office),
                'lead' => $o->project->lead?->name,
            ])
            ->all();

        return [
            'columns' => $this->columns([
                'date' => 'Date', 'type' => 'Type', 'title' => 'Title', 'venue' => 'Journal/Venue/Adopter', 'indexing' => 'Indexing',
                'citations' => 'Citations', 'project' => 'Source Record', 'office' => 'Office', 'lead' => 'Lead',
            ]),
            'rows' => $rows,
            'totals' => ['date' => count($rows).' outcome(s)', 'citations' => array_sum(array_column($rows, 'citations'))],
        ];
    }

    private function certificates(User $user, array $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->baseCertificates($user, $filters)
            ->whereBetween('issued_at', [$from, $to])
            ->with(['recipient:id,name', 'issuer:id,name', 'project:id,title,office'])
            ->orderByDesc('issued_at')
            ->get()
            ->map(fn (Certificate $c) => [
                'serial_no' => $c->serial_no,
                'issued_at' => $c->issued_at?->toDateString(),
                'recipient' => $c->recipient->name,
                'type' => ucfirst($c->type),
                'purpose' => ucfirst($c->purpose ?? ''),
                'project' => $c->project->title,
                'office' => ucfirst($c->project->office),
                'issued_by' => $c->issuer?->name,
            ])
            ->all();

        return [
            'columns' => $this->columns([
                'serial_no' => 'Serial No.', 'issued_at' => 'Date Issued', 'recipient' => 'Recipient', 'type' => 'Type',
                'purpose' => 'Purpose', 'project' => 'Record', 'office' => 'Office', 'issued_by' => 'Issued By',
            ]),
            'rows' => $rows,
            'totals' => ['serial_no' => count($rows).' certificate(s)'],
        ];
    }

    private function ipcr(User $user, array $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $people = User::query()
            ->whereIn('role', User::SUBMITTERS)
            ->with(['unit:id,code,name', 'campus:id,name'])
            ->when($user->hasRole('faculty'), fn (Builder $q) => $q->whereKey($user->id))
            ->when($user->hasRole('head') && $user->unit_id, fn (Builder $q) => $q->where('unit_id', $user->unit_id))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->whereKey($id))
            ->when($filters['campus_id'] ?? null, fn (Builder $q, $id) => $q->where('campus_id', $id))
            ->when($filters['unit_id'] ?? null, fn (Builder $q, $id) => $q->where('unit_id', $id))
            ->orderBy('name')
            ->get();

        // One query for everyone's records, grouped by person in memory
        $ids = $people->pluck('id');
        $allProjects = $this->baseProjects($user, $filters)
            ->where(fn (Builder $q) => $q->whereIn('lead_id', $ids)
                ->orWhereHas('members', fn (Builder $m) => $m->whereIn('user_id', $ids)))
            ->with('outcomes', 'certificates', 'members:id,project_id,user_id')
            ->get();

        $byPerson = [];
        foreach ($allProjects as $project) {
            foreach ($project->members->pluck('user_id')->push($project->lead_id)->filter()->unique() as $personId) {
                $byPerson[$personId][] = $project;
            }
        }

        $rows = [];
        foreach ($people as $person) {
            $projects = collect($byPerson[$person->id] ?? []);
            $outcomes = $projects->flatMap->outcomes->filter(fn ($o) => $o->date && $o->date->betweenIncluded($from, $to));
            $certs = $projects->flatMap->certificates->filter(fn ($c) => $c->user_id === $person->id && $c->status === 'issued' && $c->issued_at?->betweenIncluded($from, $to));

            $row = [
                'name' => $person->name,
                'unit' => $person->unit?->code ?? $person->unit?->name,
                'campus' => $person->campus?->name,
                'proposals' => $projects->filter(fn ($p) => $p->proposed_at?->betweenIncluded($from, $to))->count(),
                'ongoing' => $projects->where('stage', 'ongoing')->count(),
                'completed' => $projects->filter(fn ($p) => $p->completed_at?->betweenIncluded($from, $to))->count(),
                'publications' => $outcomes->where('type', 'publication')->count(),
                'scopus' => $outcomes->where('type', 'publication')->where('indexing', 'scopus')->count(),
                'utilization' => $outcomes->whereIn('type', self::UTILIZATION)->count(),
                'certificates' => $certs->count(),
            ];

            if ($filters['active_only'] ?? true) {
                $activity = $row['proposals'] + $row['ongoing'] + $row['completed'] + $row['publications'] + $row['utilization'];
                if ($activity === 0 && ! ($filters['user_id'] ?? null) && ! $user->hasRole('faculty')) {
                    continue;
                }
            }

            $rows[] = $row;
        }

        return [
            'columns' => $this->columns([
                'name' => 'Faculty/Personnel', 'unit' => 'College/Unit', 'campus' => 'Campus', 'proposals' => 'Proposals',
                'ongoing' => 'Ongoing', 'completed' => 'Completed', 'publications' => 'Publications', 'scopus' => 'Scopus',
                'utilization' => 'Utilized', 'certificates' => 'Certificates',
            ]),
            'rows' => $rows,
            'totals' => $this->sumRows($rows, 'name', ['unit', 'campus']),
        ];
    }

    private function opcr(User $user, array $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $offices = $filters['office'] ?? null ? [$filters['office']] : ($user->office && $user->hasRole('staff', 'director') ? [$user->office] : User::OFFICES);
        $year = (int) ($filters['year'] ?? $to->year);
        $yearFrom = CarbonImmutable::create($year)->startOfYear();
        $yearTo = $yearFrom->endOfYear();

        $rows = [];
        foreach ($offices as $office) {
            $scoped = [...$filters, 'office' => $office];
            $projects = $this->baseProjects($user, $scoped)->get(['id', 'stage', 'proposed_at', 'completed_at']);
            $outcomes = $this->baseOutcomes($user, $scoped)->whereBetween('date', [$yearFrom, $yearTo])->get(['type', 'indexing']);
            $targets = Target::where('year', $year)->where('office', $office)->pluck('value', 'metric');

            $actual = [
                'proposals' => $projects->filter(fn ($p) => $p->proposed_at?->betweenIncluded($yearFrom, $yearTo))->count(),
                'ongoing' => $projects->where('stage', 'ongoing')->count(),
                'completed' => $projects->filter(fn ($p) => $p->completed_at?->betweenIncluded($yearFrom, $yearTo))->count(),
                'publications' => $outcomes->where('type', 'publication')->count(),
                'scopus' => $outcomes->where('type', 'publication')->where('indexing', 'scopus')->count(),
                'commercialization' => $outcomes->where('type', 'commercialization')->count(),
                'policy' => $outcomes->where('type', 'policy')->count(),
                'technology_transfer' => $outcomes->where('type', 'technology_transfer')->count(),
            ];

            foreach (Target::METRICS as $metric) {
                $target = $targets[$metric] ?? null;
                $rows[] = [
                    'office' => ucfirst($office),
                    'metric' => ucwords(str_replace('_', ' ', $metric)),
                    'target' => $target,
                    'accomplished' => $actual[$metric],
                    'rate' => $target ? round($actual[$metric] / $target * 100, 1).'%' : '—',
                ];
            }
        }

        return [
            'columns' => $this->columns([
                'office' => 'Office', 'metric' => 'Success Indicator', 'target' => 'Target ('.$year.')',
                'accomplished' => 'Actual Accomplishment', 'rate' => 'Accomplishment Rate',
            ]),
            'rows' => $rows,
            'totals' => null,
        ];
    }

    private function ched(User $user, array $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $projects = $this->baseProjects($user, $filters)->with(['campus:id,name', 'outcomes'])->get();

        $rows = $projects
            ->groupBy(fn (Project $p) => ($p->campus?->name ?? 'Unassigned').'|'.$p->office)
            ->map(function (Collection $group, string $key) use ($from, $to) {
                [$campus, $office] = explode('|', $key);
                $outcomes = $group->flatMap->outcomes->filter(fn ($o) => $o->date && $o->date->betweenIncluded($from, $to));

                return [
                    'campus' => $campus,
                    'office' => ucfirst($office),
                    'proposals' => $group->filter(fn ($p) => $p->proposed_at?->betweenIncluded($from, $to))->count(),
                    'ongoing' => $group->where('stage', 'ongoing')->count(),
                    'completed' => $group->filter(fn ($p) => $p->completed_at?->betweenIncluded($from, $to))->count(),
                    'publications' => $outcomes->where('type', 'publication')->count(),
                    'scopus' => $outcomes->where('type', 'publication')->where('indexing', 'scopus')->count(),
                    'utilization' => $outcomes->whereIn('type', self::UTILIZATION)->count(),
                    'budget' => (float) $group->sum('budget'),
                ];
            })
            ->sortBy(['campus', 'office'])
            ->values()
            ->all();

        return [
            'columns' => $this->columns([
                'campus' => 'Campus', 'office' => 'Office', 'proposals' => 'Proposals', 'ongoing' => 'Ongoing',
                'completed' => 'Completed', 'publications' => 'Publications', 'scopus' => 'Scopus', 'utilization' => 'Utilized',
                'budget' => 'Total Budget (PHP)',
            ]),
            'rows' => $rows,
            'totals' => $this->sumRows($rows, 'campus', ['office']),
        ];
    }

    private function baseProjects(User $user, array $filters): Builder
    {
        return Project::query()
            ->verified()
            ->withinScopeOf($user)
            ->when($filters['office'] ?? null, fn (Builder $q, $v) => $q->where('office', $v))
            ->when($filters['campus_id'] ?? null, fn (Builder $q, $v) => $q->where('campus_id', $v))
            ->when($filters['unit_id'] ?? null, fn (Builder $q, $v) => $q->where('unit_id', $v))
            ->when($filters['stage'] ?? null, fn (Builder $q, $v) => $q->where('stage', $v));
    }

    private function baseOutcomes(User $user, array $filters): Builder
    {
        return ProjectOutcome::query()->whereHas('project', fn (Builder $q) => $q
            ->verified()
            ->withinScopeOf($user)
            ->when($filters['office'] ?? null, fn (Builder $q, $v) => $q->where('office', $v))
            ->when($filters['campus_id'] ?? null, fn (Builder $q, $v) => $q->where('campus_id', $v))
            ->when($filters['unit_id'] ?? null, fn (Builder $q, $v) => $q->where('unit_id', $v)));
    }

    private function baseCertificates(User $user, array $filters): Builder
    {
        return Certificate::query()
            ->where('status', 'issued')
            ->when($user->hasRole('faculty'), fn (Builder $q) => $q->where('user_id', $user->id))
            ->whereHas('project', fn (Builder $q) => $q
                ->when(! $user->hasRole('faculty'), fn (Builder $q) => $q->withinScopeOf($user))
                ->when($filters['office'] ?? null, fn (Builder $q, $v) => $q->where('office', $v))
                ->when($filters['campus_id'] ?? null, fn (Builder $q, $v) => $q->where('campus_id', $v))
                ->when($filters['unit_id'] ?? null, fn (Builder $q, $v) => $q->where('unit_id', $v)));
    }

    /**
     * @return array<string, string> period key => label
     */
    private function periods(CarbonImmutable $from, CarbonImmutable $to, string $groupBy): array
    {
        $periods = [];
        $cursor = match ($groupBy) {
            'month' => $from->startOfMonth(),
            'quarter' => $from->startOfQuarter(),
            default => $from->startOfYear(),
        };

        while ($cursor->lessThanOrEqualTo($to)) {
            $periods[$this->periodKey($cursor, $groupBy)] = match ($groupBy) {
                'month' => $cursor->format('M Y'),
                'quarter' => 'Q'.$cursor->quarter.' '.$cursor->year,
                default => (string) $cursor->year,
            };
            $cursor = match ($groupBy) {
                'month' => $cursor->addMonth(),
                'quarter' => $cursor->addQuarter(),
                default => $cursor->addYear(),
            };
        }

        return $periods;
    }

    private function periodKey(CarbonInterface $date, string $groupBy): string
    {
        return match ($groupBy) {
            'month' => $date->format('Y-m'),
            'quarter' => $date->year.'-Q'.$date->quarter,
            default => (string) $date->year,
        };
    }

    private function columns(array $map): array
    {
        return collect($map)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
    }

    private function sumRows(array $rows, string $labelKey, array $skip = []): array
    {
        $totals = [$labelKey => 'Total'];
        foreach ($rows as $row) {
            foreach ($row as $key => $value) {
                if ($key !== $labelKey && ! in_array($key, $skip, true) && is_numeric($value)) {
                    $totals[$key] = ($totals[$key] ?? 0) + $value;
                }
            }
        }

        return $totals;
    }

    private function scopeLabel(User $user): string
    {
        return match ($user->role) {
            'faculty' => 'Own records of '.$user->name,
            'head' => 'College/Unit: '.($user->unit?->name ?? 'own records'),
            'staff', 'director' => $user->office ? ucfirst($user->office).' Office' : 'All offices',
            default => 'University-wide',
        };
    }
}
