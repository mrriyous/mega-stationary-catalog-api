<?php

namespace App\Services;

use App\Models\Category;
use App\Models\SyncChange;
use App\Models\Video;
use App\Models\VideoSortData;
use App\Support\SyncPayload;

final class SyncChangeService
{
    public function recordCategory(Category $category): void
    {
        $this->write('category', $category->id, 'upsert', SyncPayload::category($category));
    }

    public function deleteCategory(int $id): void
    {
        $this->write('category', $id, 'delete', ['id' => $id]);
    }

    public function recordVideo(Video $video): void
    {
        $this->write('video', $video->id, 'upsert', SyncPayload::video($video));
    }

    public function deleteVideo(int $id): void
    {
        $this->write('video', $id, 'delete', ['id' => $id]);
    }

    public function recordVideoSort(VideoSortData $sort): void
    {
        $this->write('video_sort', $sort->video_id, 'upsert', VideoSortService::payload($sort));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function write(string $entityType, int $entityId, string $action, array $payload): void
    {
        SyncChange::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'payload' => $payload,
        ]);
    }
}
