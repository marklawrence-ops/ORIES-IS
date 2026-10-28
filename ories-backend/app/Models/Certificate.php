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

    /**
     * Why the record does not qualify for this certificate type, or null when it does.
     */
    public static function eligibilityError(Project $project, string $type): ?string
    {
        return match ($type) {
            'completion' => $project->stage === 'completed' ? null : 'A completion certificate requires a completed record.',
            'publication' => $project->outcomes->contains('type', 'publication') ? null : 'A publication certificate requires a verified publication.',
            'utilization' => $project->outcomes->whereIn('type', ['commercialization', 'policy', 'technology_transfer'])->isNotEmpty()
                ? null
                : 'A utilization certificate requires a verified commercialization, policy, or technology transfer outcome.',
            default => null,
        };
    }

    /**
     * Every certificate type with the reason the person cannot request it now (null = can request).
     *
     * @return array<string, ?string>
     */
    public static function availabilityFor(Project $project, User $user): array
    {
        $held = self::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['requested', 'issued'])
            ->pluck('status', 'type');

        return collect(self::TYPES)->mapWithKeys(fn (string $type) => [
            $type => self::eligibilityError($project, $type)
                ?? (isset($held[$type]) ? ($held[$type] === 'issued' ? 'You already have this certificate.' : 'You already requested this certificate.') : null),
        ])->all();
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
