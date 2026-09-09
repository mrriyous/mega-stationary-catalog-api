<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SyncChange;
use App\Models\Video;
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
        $videos = Video::query()
            ->where('id', '>', $afterVideoId)
            ->orderBy('id')
            ->limit(50)
            ->get();

        return response()->json([
            'snapshot_cursor' => SyncChange::max('id') ?? 0,
            'categories' => $afterVideoId === 0
                ? Category::orderBy('sort_order')->get()->map(SyncPayload::category(...))
                : [],
            'videos' => $videos->map(SyncPayload::video(...)),
            'next_video_id' => $videos->last()?->id ?? $afterVideoId,
            'has_more' => $videos->count() === 50,
        ]);
    }
}
