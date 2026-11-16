<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DutySchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'library_id',
        'task_key',
        'shift_date',
        'start_time',
        'end_time',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    /**
     * Scope query to schedules valid for a specific date and task.
     */
    public function scopeForDateAndTask(Builder $query, string $date, string $taskKey): Builder
    {
        return $query->where('shift_date', $date)
            ->where('task_key', $taskKey)
            ->whereIn('status', ['scheduled', 'active']);
    }

    /**
     * Scope query for today's active turns.
     */
    public function scopeActiveToday(Builder $query): Builder
    {
        $today = Carbon::today()->toDateString();
        return $query->where('shift_date', $today)
            ->whereIn('status', ['scheduled', 'active']);
    }
}
