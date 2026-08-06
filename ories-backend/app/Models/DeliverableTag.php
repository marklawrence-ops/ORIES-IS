<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliverableTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'deliverable_id',
        'tagged_campus_id',
        'tagged_department',
        'collaborator_name',
    ];

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }

    public function taggedCampus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'tagged_campus_id');
    }
}