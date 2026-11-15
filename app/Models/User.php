<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'status',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get all duty schedules assigned to this user.
     */
    public function dutySchedules(): HasMany
    {
        return $this->hasMany(DutySchedule::class);
    }

    /**
     * Loans issued by this staff member.
     */
    public function issuedLoans(): HasMany
    {
        return $this->hasMany(BookLoan::class, 'issued_by_user_id');
    }

    /**
     * Loans borrowed by this student/patron.
     */
    public function borrowedLoans(): HasMany
    {
        return $this->hasMany(BookLoan::class, 'member_id');
    }

    /**
     * Check if user is Super Admin or Head Librarian (administrative override).
     */
    public function hasAdminPrivilege(): bool
    {
        return $this->hasRole(['Super Admin', 'Head Librarian']);
    }

    /**
     * Get list of direct permissions granted explicitly to this user.
     *
     * @return array<int, string>
     */
    public function getDirectPermissionsList(): array
    {
        return $this->getDirectPermissions()->pluck('name')->values()->all();
    }

    /**
     * Get list of all permissions (via roles + direct user overrides).
     *
     * @return array<int, string>
     */
    public function getAllPermissionsList(): array
    {
        return $this->getAllPermissions()->pluck('name')->values()->all();
    }
}
