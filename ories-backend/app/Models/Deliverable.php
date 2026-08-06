<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deliverable extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'office_type',
        'campus_id',
        'head_id',
        'status',
        'due_date',
        'mov_link'
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(DeliverableTag::class);
    }
}