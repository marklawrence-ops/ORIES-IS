<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ProjectUpdate extends Model
{
    public const TYPES = ['proposal', 'stage', 'outcome', 'details'];

    // A submitter may withdraw a pending update; it then leaves the queue without changing the record
    public const STATUSES = ['pending', 'verified', 'returned', 'withdrawn'];

    // Plain names for the fields a change of details can touch
    public const DETAIL_LABELS = [
        'title' => 'title',
        'abstract' => 'abstract',
        'keywords' => 'keywords',
        'funding_source' => 'funding source',
        'budget' => 'budget',
        'start_date' => 'start date',
        'end_date' => 'end date',
        'members' => 'members',
        'tags' => 'contributor tags',
    ];

    protected $fillable = [
        'project_id',
        'submitted_by',
        'type',
        'from_stage',
        'to_stage',
        'data',
        'remarks',
        'duplicate_matches',
        'status',
        'reviewed_by',
        'review_remarks',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'duplicate_matches' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Pending updates in the user's verification queue (their office, or every office).
     */
    public function scopeInQueueOf(Builder $query, User $user): Builder
    {
        return $query->where('status', 'pending')
            ->whereHas('project', fn (Builder $q) => $user->office ? $q->where('office', $user->office) : $q);
    }

    /**
     * Pending updates that have waited longer than the verification window.
     */
    public function scopeWaitingTooLong(Builder $query): Builder
    {
        return $query->where('status', 'pending')->where('created_at', '<', self::waitingCutoff());
    }

    /**
     * Submissions made before this moment have waited more than `verify_within_days` working days.
     */
    public static function waitingCutoff(): Carbon
    {
        return now()->subWeekdays(config('ories.verify_within_days'));
    }

    public function waitingDays(): int
    {
        return (int) $this->created_at->diffInWeekdays(now());
    }

    public function summary(): string
    {
        return match ($this->type) {
            'proposal' => 'New proposal',
            'stage' => 'Stage update: '.ucfirst((string) $this->from_stage).' → '.ucfirst((string) $this->to_stage),
            'outcome' => 'New outcome: '.str_replace('_', ' ', (string) ($this->data['type'] ?? 'outcome')),
            'details' => 'Change of details: '.collect([...array_keys($this->data['fields'] ?? []), ...array_keys(collect($this->data)->only(['members', 'tags'])->all())])
                ->map(fn (string $key) => self::DETAIL_LABELS[$key] ?? $key)->join(', '),
            default => 'Update',
        };
    }
}
