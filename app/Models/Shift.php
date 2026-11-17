<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_am',
        'code',
        'start_time',
        'end_time',
        'ethiopian_start_time',
        'ethiopian_end_time',
        'description',
        'library_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(GroupShiftSchedule::class);
    }
}
