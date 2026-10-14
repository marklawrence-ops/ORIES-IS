<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    public const TYPES = ['participation', 'completion', 'publication', 'utilization'];

    public const PURPOSES = ['promotion', 'reclassification', 'ipcr', 'other'];

    protected $fillable = [
        'project_id',
        'user_id',
        'type',
        'purpose',
        'request_remarks',
        'status',
        'serial_no',
        'verification_code',
        'issued_by',
        'issued_at',
        'decision_remarks',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
