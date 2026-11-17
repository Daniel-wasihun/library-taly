<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Library extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_am',
        'code',
        'slug',
        'location',
        'phone',
        'email',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($library) {
            if (empty($library->slug)) {
                $library->slug = Str::slug($library->name);
            }
            if (empty($library->code)) {
                $library->code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $library->name), 0, 6));
            } else {
                $library->code = strtoupper($library->code);
            }
        });
    }

    /**
     * Users/staff assigned directly to this library branch.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Physical attendances recorded at this branch.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(LibraryAttendance::class);
    }

    /**
     * Duty schedules for this branch.
     */
    public function dutySchedules(): HasMany
    {
        return $this->hasMany(DutySchedule::class);
    }

    /**
     * Shift groups for this branch.
     */
    public function shiftGroups(): HasMany
    {
        return $this->hasMany(ShiftGroup::class);
    }

    /**
     * Group shift schedules for this branch.
     */
    public function groupShiftSchedules(): HasMany
    {
        return $this->hasMany(GroupShiftSchedule::class);
    }

    /**
     * Scope for active library branches.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
