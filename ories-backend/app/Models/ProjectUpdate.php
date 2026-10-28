<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectUpdate extends Model
{
    public const TYPES = ['proposal', 'stage', 'outcome', 'details'];

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
