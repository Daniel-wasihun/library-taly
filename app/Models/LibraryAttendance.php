<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LibraryAttendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'library_id',
        'user_type',
        'gender',
        'visitor_count',
        'entry_at',
        'staff_id',
    ];

    protected $casts = [
        'entry_at' => 'datetime',
        'visitor_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = (string) Str::uuid();
            }
            if (empty($model->entry_at)) {
                $model->entry_at = now();
            }
        });
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
