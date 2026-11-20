<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles {
        hasPermissionTo as traitHasPermissionTo;
        hasDirectPermission as traitHasDirectPermission;
        hasAnyPermission as traitHasAnyPermission;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'library_id',
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
     * Get the library branch this user is assigned to (null = General / All Branches).
     */
    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function shiftGroups(): BelongsToMany
    {
        return $this->belongsToMany(ShiftGroup::class, 'shift_group_user')
            ->withTimestamps();
    }

    /**
     * Determine if user has global management access across all library branches.
     * Only Admin / Super Admin OR Managers assigned to General (library_id = null) can manage all branches.
     */
    public function canManageAllLibraries(): bool
    {
        if ($this->hasAdminPrivilege()) {
            return true;
        }

        // Managers or users with libraries.manage assigned to General (library_id = null)
        if (($this->hasRole('Manager') || $this->can('libraries.manage')) && is_null($this->library_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can manage library branches.
     */
    public function canManageLibrary(): bool
    {
        return $this->hasAdminPrivilege()
            || $this->hasRole('Manager')
            || $this->can('libraries.manage');
    }

    /**
     * Determine if user can view the overview dashboard.
     */
    public function canViewDashboard(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasAdminPrivilege()
            || $this->hasRole(['Super Admin', 'Admin', 'Manager'])
            || $this->can('dashboard.view');
    }

    /**
     * Determine if user can access the Library Branches management view.
     */
    public function canViewLibraryList(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasAdminPrivilege()
            || $this->hasRole(['Super Admin', 'Admin', 'Manager'])
            || $this->can('libraries.view')
            || $this->can('libraries.manage');
    }

    /**
     * Determine if user can create a library branch.
     */
    public function canCreateLibrary(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasAdminPrivilege()
            || $this->can('libraries.create')
            || $this->can('libraries.manage');
    }

    /**
     * Determine if user can update a library branch.
     */
    public function canUpdateLibrary(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasAdminPrivilege()
            || $this->can('libraries.update')
            || $this->can('libraries.manage');
    }

    /**
     * Determine if user can delete a library branch.
     */
    public function canDeleteLibrary(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasAdminPrivilege()
            || $this->can('libraries.delete')
            || $this->can('libraries.manage');
    }

    /**
     * Determine if user can access reporting and analytics.
     * Permitted only for users with library management permission or attendance analytics.
     */
    public function canManageReportingAndAnalytics(): bool
    {
        return $this->hasAdminPrivilege()
            || $this->hasRole('Manager')
            || $this->can('libraries.manage')
            || $this->can('attendance.analytics');
    }

    /**
     * Determine if user can view academic years.
     */
    public function canViewAcademicYears(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasAdminPrivilege()
            || $this->hasRole(['Super Admin', 'Admin', 'Manager', 'Librarian'])
            || $this->can('libraries.view')
            || $this->can('attendance.view')
            || $this->can('attendance.analytics');
    }

    /**
     * Determine if user can manage academic years.
     */
    public function canManageAcademicYears(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasAdminPrivilege()
            || $this->hasRole(['Super Admin', 'Admin', 'Manager'])
            || $this->can('libraries.manage')
            || $this->can('attendance.analytics');
    }

    /**
     * Check if user can perform operations at a target library branch.
     */
    public function canAccessLibrary(?int $targetLibraryId): bool
    {
        if ($this->canManageAllLibraries()) {
            return true;
        }

        if (is_null($targetLibraryId)) {
            return false;
        }

        return (int) $this->library_id === (int) $targetLibraryId;
    }

    /**
     * Get all duty schedules assigned to this user.
     */
    public function dutySchedules(): HasMany
    {
        return $this->hasMany(DutySchedule::class);
    }

    /**
     * Physical attendances recorded by this staff member.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(LibraryAttendance::class, 'staff_id');
    }

    /**
     * Check if user is Super Admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Super Admin');
    }

    /**
     * Check if user has administrative master override (Super Admin or Admin).
     */
    public function hasAdminPrivilege(): bool
    {
        return $this->isSuperAdmin() || $this->hasRole(['Super Admin', 'Admin']);
    }

    /**
     * Super Admin possesses master permission override for any required permission.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->traitHasPermissionTo($permission, $guardName);
    }

    /**
     * Super Admin possesses master direct permission override.
     */
    public function hasDirectPermission($permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->traitHasDirectPermission($permission);
    }

    /**
     * Super Admin possesses master any-permission override.
     */
    public function hasAnyPermission(...$permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->traitHasAnyPermission(...$permissions);
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
