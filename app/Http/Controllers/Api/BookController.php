<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    /**
     * List all books in the university library catalog.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $category = $request->query('category');
        $availableOnly = $request->boolean('available');

        $query = Book::latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('author', 'ilike', "%{$search}%")
                    ->orWhere('isbn', 'ilike', "%{$search}%")
                    ->orWhere('shelf_location', 'ilike', "%{$search}%");
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($availableOnly) {
            $query->where('available_copies', '>', 0);
        }

        $books = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $books->items(),
            'pagination' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'total' => $books->total(),
            ],
            'categories' => Cache::remember('library_book_categories', 600, function () {
                return Book::distinct()->pluck('category')->filter()->values();
            }),
        ]);
    }

    /**
     * Add a new book to the library catalog.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'max:50', 'unique:books,isbn'],
            'author' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'shelf_location' => ['nullable', 'string', 'max:100'],
            'total_copies' => ['required', 'integer', 'min:1'],
        ]);

        $book = Book::create([
            'title' => $validated['title'],
            'isbn' => $validated['isbn'],
            'author' => $validated['author'],
            'category' => $validated['category'],
            'shelf_location' => $validated['shelf_location'] ?? 'General Stacks',
            'total_copies' => $validated['total_copies'],
            'available_copies' => $validated['total_copies'],
        ]);

        Cache::forget('library_book_categories');
        Cache::forget('dashboard_stats');

        return response()->json([
            'success' => true,
            'message' => 'Book added to catalog successfully.',
            'book' => $book,
        ], 201);
    }

    /**
     * Update an existing book record.
     */
    public function update(Request $request, Book $book): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'max:50', Rule::unique('books', 'isbn')->ignore($book->id)],
            'author' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'shelf_location' => ['nullable', 'string', 'max:100'],
            'total_copies' => ['required', 'integer', 'min:1'],
            'available_copies' => ['required', 'integer', 'min:0'],
        ]);

        $book->update($validated);

        Cache::forget('library_book_categories');
        Cache::forget('dashboard_stats');

        return response()->json([
            'success' => true,
            'message' => 'Book updated successfully.',
            'book' => $book,
        ]);
    }

    /**
     * Delete book from catalog.
     */
    public function destroy(Book $book): JsonResponse
    {
        $book->delete();

        Cache::forget('library_book_categories');
        Cache::forget('dashboard_stats');

        return response()->json([
            'success' => true,
            'message' => 'Book removed from catalog.',
        ]);
    }
}
