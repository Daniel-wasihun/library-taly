<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupShiftSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'library_id',
        'shift_group_id',
        'shift_id',
        'ethiopian_year',
        'ethiopian_month',
        'days',
        'status',
        'notes',
    ];

    protected $casts = [
        'ethiopian_year' => 'integer',
        'ethiopian_month' => 'integer',
        'days' => 'array',
    ];

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ShiftGroup::class, 'shift_group_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
