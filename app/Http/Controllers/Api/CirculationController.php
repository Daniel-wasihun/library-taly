<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CirculationController extends Controller
{
    /**
     * List all circulation loans.
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $search = $request->query('search');

        $query = BookLoan::with([
            'book:id,title,isbn,author,shelf_location',
            'member:id,name,username,email',
            'issuedBy:id,name,username',
        ])->latest('issued_date');

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->whereHas('book', function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('isbn', 'ilike', "%{$search}%");
            })->orWhereHas('member', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('username', 'ilike', "%{$search}%");
            });
        }

        $loans = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $loans->items(),
            'pagination' => [
                'current_page' => $loans->currentPage(),
                'last_page' => $loans->lastPage(),
                'total' => $loans->total(),
            ],
        ]);
    }

    /**
     * Issue/Checkout a book to a student/member.
     * Note: Access is gated by the EnsureScheduledDutyTurn middleware!
     */
    public function issue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'exists:books,id'],
            'member_id' => ['required', 'exists:users,id'],
            'due_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $book = Book::lockForUpdate()->findOrFail($validated['book_id']);

            if ($book->available_copies <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No copies currently available for borrowing.',
                ], 422);
            }

            $member = User::findOrFail($validated['member_id']);
            $dueDays = $validated['due_days'] ?? 14;

            // Decrement copies
            $book->decrement('available_copies');

            $loan = BookLoan::create([
                'book_id' => $book->id,
                'member_id' => $member->id,
                'issued_by_user_id' => $request->user()->id,
                'issued_date' => Carbon::now(),
                'due_date' => Carbon::now()->addDays($dueDays),
                'status' => 'borrowed',
                'notes' => $validated['notes'] ?? null,
            ]);

            Cache::forget('dashboard_stats');

            return response()->json([
                'success' => true,
                'message' => "Book '{$book->title}' successfully issued to {$member->name}.",
                'loan' => $loan->load(['book', 'member', 'issuedBy']),
            ], 201);
        });
    }

    /**
     * Return a borrowed book to the library.
     */
    public function returnBook(BookLoan $loan): JsonResponse
    {
        if ($loan->status === 'returned') {
            return response()->json([
                'success' => false,
                'message' => 'This book has already been marked as returned.',
            ], 422);
        }

        DB::transaction(function () use ($loan) {
            $loan->update([
                'returned_date' => Carbon::now(),
                'status' => 'returned',
            ]);

            $loan->book->increment('available_copies');
            Cache::forget('dashboard_stats');
        });

        return response()->json([
            'success' => true,
            'message' => "Book '{$loan->book->title}' has been returned successfully.",
            'loan' => $loan->fresh(['book', 'member', 'issuedBy']),
        ]);
    }
}
