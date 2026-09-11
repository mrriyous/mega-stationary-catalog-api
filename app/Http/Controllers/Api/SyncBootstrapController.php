<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SyncChange;
use App\Models\Video;
use App\Models\VideoSortData;
use App\Services\VideoSortService;
use App\Support\SyncPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncBootstrapController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'after_video_id' => ['nullable', 'integer', 'min:0'],
        ]);
        $afterVideoId = (int) ($data['after_video_id'] ?? 0);
        $pageSize = 50;
        $user = $request->user();

        $videos = Video::query()
            ->where('id', '>', $afterVideoId)
            ->orderBy('id')
            ->limit($pageSize)
            ->get();

        $categories = [];
        if ($afterVideoId === 0) {
            $categories = Category::query()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Category $category) => SyncPayload::category($category));
        }

        $videoSortData = VideoSortData::query()
            ->whereIn('video_id', $videos->pluck('id'))
            ->orderBy('video_id')
            ->orderByDesc('id')
            ->get()
            ->unique('video_id')
            ->values()
            ->map(fn (VideoSortData $sort) => VideoSortService::payload($sort));

        $snapshotCursor = SyncChange::max('id') ?? 0;
        $nextVideoId = $videos->last()?->id ?? $afterVideoId;
        $hasMore = $videos->count() === $pageSize;
        $videoData = $videos->map(fn (Video $video) => SyncPayload::video($video, $user));

        return response()->json([
            'snapshot_cursor' => $snapshotCursor,
            'categories' => $categories,
            'videos' => $videoData,
            'video_sort_data' => $videoSortData,
            'next_video_id' => $nextVideoId,
            'has_more' => $hasMore,
        ]);
    }
}
