<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Project extends Model
{
    use HasFactory;

    public const STAGES = ['proposal', 'ongoing', 'completed', 'terminated'];

    // Allowed forward moves; terminated is reachable from any active stage
    public const STAGE_TRANSITIONS = [
        'proposal' => ['ongoing', 'terminated'],
        'ongoing' => ['completed', 'terminated'],
        'completed' => [],
        'terminated' => [],
    ];

    protected $fillable = [
        'title',
        'office',
        'abstract',
        'keywords',
        'lead_id',
        'campus_id',
        'unit_id',
        'stage',
        'verification_status',
        'funding_source',
        'budget',
        'start_date',
        'end_date',
        'proposed_at',
        'completed_at',
        'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'proposed_at' => 'date:Y-m-d',
            'completed_at' => 'date:Y-m-d',
            'last_verified_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_id');
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(ProjectTag::class);
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(ProjectOutcome::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function activity(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', 'verified');
    }

    /**
     * Verified records are visible to every signed-in user; unverified records are visible
     * only to the people involved and to the reviewers responsible for them.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('verification_status', 'verified')
                ->orWhere(fn (Builder $own) => $own->involving($user));

            if ($user->hasRole('vp', 'oup')) {
                $q->orWhereRaw('1 = 1');
            } elseif ($user->hasRole('staff', 'director')) {
                $user->office ? $q->orWhere('office', $user->office) : $q->orWhereRaw('1 = 1');
            } elseif ($user->hasRole('head') && $user->unit_id) {
                $q->orWhere('unit_id', $user->unit_id);
            }
        });
    }

    /**
     * Records with a submission still waiting for a verifier.
     */
    public function scopeAwaitingVerification(Builder $query): Builder
    {
        return $query->whereHas('updates', fn (Builder $u) => $u->where('status', 'pending'));
    }

    /**
     * Records whose latest submission was returned and has not been corrected yet
     * (a correction is any newer submission, which replaces the returned one).
     */
    public function scopeReturnedForCorrection(Builder $query): Builder
    {
        return $query->whereHas('updates', fn (Builder $u) => $u
            ->where('status', 'returned')
            ->whereNotExists(fn ($newer) => $newer
                ->selectRaw('1')
                ->from('project_updates as newer')
                ->whereColumn('newer.project_id', 'project_updates.project_id')
                ->whereColumn('newer.id', '>', 'project_updates.id')));
    }

    /**
     * Verified ongoing records whose end date has passed without a completion or termination.
     */
    public function scopePastEndDate(Builder $query): Builder
    {
        return $query->verified()->where('stage', 'ongoing')->whereDate('end_date', '<', today());
    }

    /**
     * Verified ongoing records that end today or within the warning window.
     */
    public function scopeEndingSoon(Builder $query): Builder
    {
        return $query->verified()->where('stage', 'ongoing')
            ->whereDate('end_date', '>=', today())
            ->whereDate('end_date', '<=', today()->addDays(config('ories.ending_soon_days')));
    }

    /**
     * 'overdue' or 'ending_soon' for verified ongoing records, matching the scopes above.
     */
    public function deadline(): ?string
    {
        if ($this->verification_status !== 'verified' || $this->stage !== 'ongoing' || ! $this->end_date) {
            return null;
        }

        return match (true) {
            $this->end_date->lt(today()) => 'overdue',
            $this->end_date->lte(today()->addDays(config('ories.ending_soon_days'))) => 'ending_soon',
            default => null,
        };
    }

    /**
     * Records that the user leads or is a member of.
     */
    public function scopeInvolving(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('lead_id', $user->id)
                ->orWhereHas('members', fn (Builder $m) => $m->where('user_id', $user->id));
        });
    }

    /**
     * Restricts dashboards and reports to the slice of data a role is responsible for.
     */
    public function scopeWithinScopeOf(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'faculty' => $query->involving($user),
            'head' => $user->unit_id ? $query->where('unit_id', $user->unit_id) : $query->involving($user),
            'staff', 'director' => $user->office ? $query->where('office', $user->office) : $query,
            default => $query,
        };
    }

    public function isInvolved(User $user): bool
    {
        return $this->lead_id === $user->id
            || $this->members->contains('user_id', $user->id);
    }

    /**
     * Whether the user may open this record's MOV files and full history.
     */
    public function canBeInspectedBy(User $user): bool
    {
        return $this->isInvolved($user)
            || $user->hasRole('vp', 'oup')
            || ($user->hasRole('staff', 'director') && ($user->office === null || $user->office === $this->office))
            || ($user->hasRole('head') && $user->unit_id !== null && $user->unit_id === $this->unit_id);
    }

    /**
     * Members other than the lead, as submitted: accounts take their account name; the lead is skipped.
     *
     * @return list<array{user_id: int|null, name: string}>
     */
    public function normalizeMembers(array $members): array
    {
        return collect($members)
            ->map(function (array $member) {
                $account = isset($member['user_id']) ? User::find($member['user_id']) : null;

                return ['user_id' => $account?->id, 'name' => trim($account?->name ?? ($member['name'] ?? ''))];
            })
            ->reject(fn (array $m) => $m['name'] === '' || $m['user_id'] === $this->lead_id)
            ->unique(fn (array $m) => $m['user_id'] ?? 'name:'.mb_strtolower($m['name']))
            ->values()->all();
    }

    /**
     * Contributor tags with their stored labels; tags without a resolvable label are dropped.
     *
     * @return list<array{type: string, unit_id: int|null, campus_id: int|null, label: string}>
     */
    public static function normalizeTags(array $tags): array
    {
        return collect($tags)
            ->map(function (array $tag) {
                $label = match ($tag['type']) {
                    // Units on different campuses can share a name, so the label names the campus too
                    'unit' => isset($tag['unit_id']) && ($unit = Unit::with('campus:id,name')->find($tag['unit_id']))
                        ? "{$unit->name} ({$unit->campus?->name})"
                        : null,
                    'campus' => isset($tag['campus_id']) ? Campus::find($tag['campus_id'])?->name : null,
                    default => null,
                } ?? ($tag['label'] ?? null);

                return [
                    'type' => $tag['type'],
                    'unit_id' => $tag['type'] === 'unit' ? ($tag['unit_id'] ?? null) : null,
                    'campus_id' => $tag['type'] === 'campus' ? ($tag['campus_id'] ?? null) : null,
                    'label' => trim((string) $label),
                ];
            })
            ->reject(fn (array $t) => $t['label'] === '')
            ->unique(fn (array $t) => $t['type'].'|'.$t['label'])
            ->values()->all();
    }

    /**
     * Replaces the non-lead members with an already normalized list.
     */
    public function replaceMembers(array $members): void
    {
        $this->members()->where('role', '!=', 'lead')->delete();
        foreach ($members as $member) {
            $this->members()->create([...$member, 'role' => 'member']);
        }
    }

    /**
     * Replaces the contributor tags with an already normalized list.
     */
    public function replaceTags(array $tags): void
    {
        $this->tags()->delete();
        foreach ($tags as $tag) {
            $this->tags()->create($tag);
        }
    }

    public function hasPendingUpdate(): bool
    {
        return $this->updates()->where('status', 'pending')->exists();
    }

    /**
     * The lead and every member with an account, who are all told when an update is decided.
     *
     * @return Collection<int, User>
     */
    public function team(): Collection
    {
        // Reloaded, since a verified change of details may have just replaced the members
        $this->load('lead', 'members.user');

        return collect([$this->lead])
            ->merge($this->members->pluck('user'))
            ->filter()
            ->unique('id')
            ->values();
    }
}
