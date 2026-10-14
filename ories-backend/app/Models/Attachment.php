<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    protected $fillable = [
        'project_id',
        'project_update_id',
        'uploaded_by',
        'original_name',
        'path',
        'mime_type',
        'size',
        'checksum',
        'source',
        'drive_file_id',
        'drive_status',
        'drive_synced_at',
    ];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'drive_synced_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectUpdate(): BelongsTo
    {
        return $this->belongsTo(ProjectUpdate::class, 'project_update_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
