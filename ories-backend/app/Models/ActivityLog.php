<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = ['project_id', 'user_id', 'action', 'description'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public static function record(?Project $project, ?User $user, string $action, string $description): self
    {
        return self::create([
            'project_id' => $project?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
