<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'member_id',
        'issued_by_user_id',
        'issued_date',
        'due_date',
        'returned_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'datetime',
            'due_date' => 'datetime',
            'returned_date' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }
}
