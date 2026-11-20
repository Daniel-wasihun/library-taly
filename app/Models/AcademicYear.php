<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'start_date',
        'end_date',
        'is_current',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'is_current' => 'boolean',
    ];

    /**
     * Get the currently active academic year.
     */
    public static function current(): ?self
    {
        return static::where('is_current', true)->first() 
            ?? static::orderBy('start_date', 'desc')->first();
    }

    /**
     * Check if a given date falls within this academic year.
     */
    public function containsDate(string|\DateTimeInterface $date): bool
    {
        $d = is_string($date) ? substr($date, 0, 10) : $date->format('Y-m-d');
        return $d >= $this->start_date->format('Y-m-d') && $d <= $this->end_date->format('Y-m-d');
    }
}
