<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SyncChange;
use App\Support\SyncPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Category::query()
                ->withCount('videos')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:categories,name']]);
        $category = Category::create([...$data, 'sort_order' => (Category::max('sort_order') ?? -1) + 1]);
        $this->record($category);

        return response()->json(['data' => SyncPayload::category($category)], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category): JsonResponse
    {
        return response()->json(['data' => SyncPayload::category($category)]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('categories')->ignore($category)]]);
        $category->update($data);
        $this->record($category);

        return response()->json(['data' => SyncPayload::category($category)]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->authorizeAdmin($request);
        if ($category->videos()->exists()) {
            return response()->json(['message' => 'Category is still used by videos.'], 422);
        }
        $id = $category->id;
        $category->delete();
        SyncChange::create(['entity_type' => 'category', 'entity_id' => $id, 'action' => 'delete', 'payload' => ['id' => $id]]);

        return response()->json(status: 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:categories,id']]);
        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $order => $id) {
                $category = Category::findOrFail($id);
                $category->update(['sort_order' => $order]);
                $this->record($category);
            }
        });

        return response()->json(['data' => Category::orderBy('sort_order')->get()]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, 'Admin access required.');
    }

    private function record(Category $category): void
    {
        SyncChange::create(['entity_type' => 'category', 'entity_id' => $category->id, 'action' => 'upsert', 'payload' => SyncPayload::category($category)]);
    }
}
