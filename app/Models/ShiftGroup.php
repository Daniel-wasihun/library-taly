<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'library_id',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shift_group_user')
            ->withTimestamps();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(GroupShiftSchedule::class);
    }
}
