<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectOutcome extends Model
{
    public const TYPES = ['publication', 'presentation', 'commercialization', 'policy', 'technology_transfer'];

    public const INDEXING = ['scopus', 'wos', 'aci', 'other', 'none'];

    protected $fillable = ['project_id', 'type', 'title', 'venue', 'indexing', 'date', 'url', 'citations', 'notes'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'citations' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
