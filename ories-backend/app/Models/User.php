<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Operational users who submit their own records
    public const SUBMITTERS = ['faculty', 'head'];

    // Users whose role carries no write access to project records
    public const VIEWERS = ['vp', 'oup'];

    public const ROLES = ['faculty', 'head', 'staff', 'director', 'vp', 'oup'];

    public const OFFICES = ['research', 'innovation', 'extension'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'employee_type',
        'office',
        'position',
        'campus_id',
        'unit_id',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function ledProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'lead_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canSubmit(): bool
    {
        return $this->hasRole(...self::SUBMITTERS);
    }

    /**
     * Staff and directors verify updates for their own office; staff without an office verify all.
     */
    public function canVerify(string $office): bool
    {
        if (! $this->hasRole('staff', 'director')) {
            return false;
        }

        return $this->office === null || $this->office === $office;
    }

    /**
     * Only the technical staff (staff not assigned to one office) administer accounts.
     */
    public function canManageUsers(): bool
    {
        return $this->hasRole('staff') && $this->office === null;
    }

    public function scopeAdministrators(Builder $query): Builder
    {
        return $query->where('role', 'staff')->whereNull('office')->where('status', 'active');
    }

    // Emails are stored lowercase so sign-in and uniqueness ignore letter case
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }
}
