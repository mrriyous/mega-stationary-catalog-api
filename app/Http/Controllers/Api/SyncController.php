<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['cursor' => ['nullable', 'integer', 'min:0']]);

        $cursor = (int) ($data['cursor'] ?? 0);

        $latestVideoChangeIds = SyncChange::query()
            ->selectRaw('MAX(id)')
            ->where('id', '>', $cursor)
            ->where('entity_type', 'video')
            ->groupBy('entity_id');

        $changes = SyncChange::query()
            ->where('id', '>', $cursor)
            ->where(function ($query) use ($latestVideoChangeIds) {
                $query->where('entity_type', '!=', 'video')
                    ->orWhereIn('id', $latestVideoChangeIds);
            })
            ->orderBy('id')
            ->limit(200)
            ->get();

        return response()->json([
            'changes' => $changes->map(fn (SyncChange $change) => [
                'cursor' => $change->id,
                'entity_type' => $change->entity_type,
                'entity_id' => $change->entity_id,
                'action' => $change->action,
                'payload' => $change->payload,
            ]),
            'next_cursor' => $changes->last()?->id ?? $cursor,
            'has_more' => $changes->count() === 200,
        ]);
    }
}
