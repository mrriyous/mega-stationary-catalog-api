<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\SyncChangeService;
use App\Services\VideoSortService;
use App\Support\SyncPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct(
        private readonly VideoSortService $videoSorts,
        private readonly SyncChangeService $syncChanges,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->withCount('videos')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $sortOrder = (Category::max('sort_order') ?? -1) + 1;
        $category = Category::create([...$data, 'sort_order' => $sortOrder]);
        $this->syncChanges->recordCategory($category);
        $payload = SyncPayload::category($category);

        return response()->json(['data' => $payload], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category): JsonResponse
    {
        $payload = SyncPayload::category($category);

        return response()->json(['data' => $payload]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $category->update($data);
        $this->syncChanges->recordCategory($category);
        $payload = SyncPayload::category($category);

        return response()->json(['data' => $payload]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category): JsonResponse
    {
        if ($category->videos()->exists()) {
            return response()->json(['message' => 'Kategori masih digunakan oleh video.'], 422);
        }
        $id = $category->id;
        DB::transaction(function () use ($category, $id) {
            $this->videoSorts->deleteForCategory($category);
            $category->delete();
            $this->syncChanges->deleteCategory($id);
        });

        return response()->json(status: 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')->whereNull('deleted_at')],
        ]);
        $existingIds = Category::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $submittedIds = array_map('intval', $data['ids']);
        $sortedSubmittedIds = $submittedIds;
        sort($sortedSubmittedIds);
        if ($existingIds !== $sortedSubmittedIds) {
            return response()->json(['message' => 'Daftar kategori tidak lengkap atau tidak valid.'], 422);
        }
        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $order => $id) {
                $category = Category::findOrFail($id);
                $category->update(['sort_order' => $order]);
                $this->videoSorts->updateCategoryOrder($category);
                $this->syncChanges->recordCategory($category);
            }
        });

        $categories = Category::query()->orderBy('sort_order')->get();

        return response()->json(['data' => $categories]);
    }
}
