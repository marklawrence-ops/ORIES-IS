<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function hasPendingUpdate(): bool
    {
        return $this->updates()->where('status', 'pending')->exists();
    }
}
