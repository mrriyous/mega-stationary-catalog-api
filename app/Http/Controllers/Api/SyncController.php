<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncChange;
use App\Models\Video;
use App\Support\SyncPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['cursor' => ['nullable', 'integer', 'min:0']]);

        $cursor = (int) ($data['cursor'] ?? 0);

        $latestCompactChangeIds = SyncChange::query()
            ->selectRaw('MAX(id)')
            ->where('id', '>', $cursor)
            ->whereIn('entity_type', ['video', 'video_sort'])
            ->groupBy('entity_type', 'entity_id');

        $changes = SyncChange::query()
            ->where('id', '>', $cursor)
            ->where(function ($query) use ($latestCompactChangeIds) {
                $query->whereNotIn('entity_type', ['video', 'video_sort'])
                    ->orWhereIn('id', $latestCompactChangeIds);
            })
            ->orderBy('id')
            ->limit(200)
            ->get();
        $videos = Video::query()
            ->whereIn('id', $changes->where('entity_type', 'video')->where('action', 'upsert')->pluck('entity_id'))
            ->get()
            ->keyBy('id');

        $nextCursor = $changes->last()?->id ?? $cursor;
        $hasMore = $changes->count() === 200;
        $data = $changes->map(function (SyncChange $change) use ($request, $videos) {
            $video = null;
            if ($change->entity_type === 'video' && $change->action === 'upsert') {
                $video = $videos->get($change->entity_id);
            }
            $action = $change->action;
            if ($change->entity_type === 'video' && ! $video) {
                $action = 'delete';
            }
            if ($video) {
                $payload = SyncPayload::video($video, $request->user());
            } elseif ($change->entity_type === 'video') {
                $payload = ['id' => $change->entity_id];
            } else {
                $payload = $change->payload;
            }

            return [
                'cursor' => $change->id,
                'entity_type' => $change->entity_type,
                'entity_id' => $change->entity_id,
                'action' => $action,
                'payload' => $payload,
            ];
        });

        return response()->json([
            'changes' => $data,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ]);
    }
}
