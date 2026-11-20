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
     * Enforces strict dashboard.view permission requirement.
     */
    public function canViewDashboard(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('dashboard.view');
    }

    /**
     * Determine if user can access the Library Branches management view.
     */
    public function canViewLibraryList(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('libraries.view')
            || $this->hasPermissionTo('libraries.manage');
    }

    /**
     * Determine if user can create a library branch.
     */
    public function canCreateLibrary(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('libraries.create')
            || $this->hasPermissionTo('libraries.manage');
    }

    /**
     * Determine if user can update a library branch.
     */
    public function canUpdateLibrary(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('libraries.update')
            || $this->hasPermissionTo('libraries.manage');
    }

    /**
     * Determine if user can delete a library branch.
     */
    public function canDeleteLibrary(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('libraries.delete')
            || $this->hasPermissionTo('libraries.manage');
    }

    /**
     * Determine if user can access reporting and analytics.
     */
    public function canManageReportingAndAnalytics(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('attendance.analytics')
            || $this->hasPermissionTo('libraries.manage');
    }

    /**
     * Determine if user can view academic years.
     */
    public function canViewAcademicYears(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('academic_years.view')
            || $this->hasPermissionTo('libraries.view');
    }

    /**
     * Determine if user can manage academic years.
     */
    public function canManageAcademicYears(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('academic_years.create')
            || $this->hasPermissionTo('academic_years.update')
            || $this->hasPermissionTo('academic_years.delete')
            || $this->hasPermissionTo('libraries.manage');
    }

    /**
     * Determine if user can view user accounts.
     */
    public function canViewUsers(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('users.view');
    }

    /**
     * Determine if user can create user accounts.
     */
    public function canCreateUsers(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('users.create');
    }

    /**
     * Determine if user can update user accounts.
     */
    public function canUpdateUsers(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('users.update');
    }

    /**
     * Determine if user can delete user accounts.
     */
    public function canDeleteUsers(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('users.delete');
    }

    /**
     * Determine if user can view roles and permissions.
     */
    public function canViewRoles(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('roles.view');
    }

    /**
     * Determine if user can manage roles (create, update, delete).
     */
    public function canManageRoles(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('roles.create')
            || $this->hasPermissionTo('roles.update')
            || $this->hasPermissionTo('roles.delete');
    }

    /**
     * Determine if user can view duty schedules.
     */
    public function canViewSchedules(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('schedules.view');
    }

    /**
     * Determine if user can manage duty schedules.
     */
    public function canManageSchedules(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('schedules.manage');
    }

    /**
     * Determine if user can view physical attendance records.
     */
    public function canViewAttendance(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermissionTo('attendance.view')
            || $this->hasPermissionTo('attendance.record');
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
