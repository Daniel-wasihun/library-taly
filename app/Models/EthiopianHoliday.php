<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EthiopianHoliday extends Model
{
    use HasFactory;

    protected $fillable = [
        'ethiopian_year',
        'ethiopian_month',
        'ethiopian_day',
        'name',
        'name_am',
        'description',
        'library_id',
        'created_by',
    ];

    protected $casts = [
        'ethiopian_year' => 'integer',
        'ethiopian_month' => 'integer',
        'ethiopian_day' => 'integer',
    ];

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
