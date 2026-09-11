<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Video;
use App\Models\VideoSortData;
use App\Services\VideoSortService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VideoSortController extends Controller
{
    public function __construct(private readonly VideoSortService $videoSorts) {}

    public function index(Category $category): JsonResponse
    {
        $sortData = VideoSortData::query()
            ->selectRaw('video_id, MIN(video_order) as video_order')
            ->groupBy('video_id');

        $videos = Video::query()
            ->where('videos.category_id', $category->id)
            ->leftJoinSub($sortData, 'video_sort', 'video_sort.video_id', '=', 'videos.id')
            ->select('videos.id', 'videos.product_code', 'videos.product_name', 'videos.cover_path')
            ->orderByRaw('COALESCE(video_sort.video_order, 2147483647)')
            ->orderBy('videos.id')
            ->get()
            ->map(function (Video $video) {
                $coverUrl = $video->cover_path ? route('videos.cover', $video, false) : null;

                return [
                    ...$video->only('id', 'product_code', 'product_name'),
                    'cover_url' => $coverUrl,
                ];
            });

        return response()->json(['data' => $videos]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $existingIds = $category->videos()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $submittedIds = array_map('intval', $data['ids']);
        $sortedExisting = $existingIds;
        $sortedSubmitted = $submittedIds;

        sort($sortedExisting);
        sort($sortedSubmitted);

        if ($sortedExisting !== $sortedSubmitted) {
            return response()->json(['message' => 'Daftar video kategori tidak lengkap atau tidak valid.'], 422);
        }

        DB::transaction(fn () => $this->videoSorts->reorder($category, $submittedIds));

        return response()->json(['message' => 'Urutan video berhasil disimpan.']);
    }
}
