<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Library;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LibraryController extends Controller
{
    /**
     * Display a listing of library branches.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->canViewLibraryList() && !$user->hasPermissionTo('attendance.record') && !$user->hasPermissionTo('attendance.view')) {
            return response()->json([
                'message' => 'Unauthorized: Permission required to view library branches.',
            ], 403);
        }

        $search = $request->query('search');
        $activeOnly = $request->query('active_only');

        $query = Library::query()
            ->withCount([
                'users',
                'attendances',
                'dutySchedules',
            ])
            ->orderBy('name', 'asc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('name_am', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%")
                    ->orWhere('location', 'ilike', "%{$search}%");
            });
        }

        if ($activeOnly === 'true' || $activeOnly === '1') {
            $query->where('is_active', true);
        }

        $libraries = $query->get();

        return response()->json([
            'success' => true,
            'data' => $libraries,
        ]);
    }

    /**
     * Store a newly created library branch in storage.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->canCreateLibrary()) {
            return response()->json([
                'message' => 'Unauthorized: Permission required to create library branches.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:libraries,name'],
            'name_am' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10', 'unique:libraries,code'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $code = strtoupper(trim($validated['code']));
        $slug = Str::slug($validated['name']);

        $library = Library::create([
            'name' => $validated['name'],
            'name_am' => $validated['name_am'] ?? null,
            'code' => $code,
            'slug' => $slug,
            'location' => $validated['location'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Library branch created successfully.',
            'data' => $library->loadCount(['users', 'books']),
        ], 201);
    }

    /**
     * Display the specified library branch.
     */
    public function show(Library $library): JsonResponse
    {
        $library->loadCount([
            'users',
            'books',
            'bookLoans as active_loans_count' => function ($q) {
                $q->where('status', 'borrowed');
            },
        ]);

        $library->load([
            'users' => function ($q) {
                $q->with('roles')->latest()->take(10);
            },
        ]);

        return response()->json([
            'success' => true,
            'data' => $library,
        ]);
    }

    /**
     * Update the specified library branch in storage.
     */
    public function update(Request $request, Library $library): JsonResponse
    {
        if (!$request->user()->canUpdateLibrary()) {
            return response()->json([
                'message' => 'Unauthorized: Permission required to update library branches.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('libraries', 'name')->ignore($library->id)],
            'name_am' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10', Rule::unique('libraries', 'code')->ignore($library->id)],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $library->update([
            'name' => $validated['name'],
            'name_am' => $validated['name_am'] ?? $library->name_am,
            'code' => strtoupper(trim($validated['code'])),
            'slug' => Str::slug($validated['name']),
            'location' => $validated['location'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : $library->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Library branch updated successfully.',
            'data' => $library->loadCount(['users', 'attendances']),
        ]);
    }

    /**
     * Remove the specified library branch from storage.
     */
    public function destroy(Request $request, Library $library): JsonResponse
    {
        if (!$request->user()->canDeleteLibrary()) {
            return response()->json([
                'message' => 'Unauthorized: Permission required to delete library branches.',
            ], 403);
        }

        $library->delete();

        return response()->json([
            'success' => true,
            'message' => 'Library branch deleted successfully.',
        ]);
    }
}
