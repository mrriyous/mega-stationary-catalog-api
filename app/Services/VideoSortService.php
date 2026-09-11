<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Video;
use App\Models\VideoSortData;

final class VideoSortService
{
    public function __construct(private readonly SyncChangeService $syncChanges) {}

    public function createFor(Video $video): VideoSortData
    {
        $category = Category::query()->lockForUpdate()->findOrFail($video->category_id);
        VideoSortData::where('video_id', $video->id)->delete();
        $position = (VideoSortData::where('category_id', $category->id)->max('video_order') ?? -1) + 1;
        $sort = VideoSortData::create([
            'video_id' => $video->id,
            'category_id' => $category->id,
            'category_order' => $category->sort_order,
            'video_order' => $position,
        ]);
        $this->syncChanges->recordVideoSort($sort);

        return $sort;
    }

    public function move(Video $video, int $previousCategoryId): void
    {
        if ($previousCategoryId === (int) $video->category_id) {
            return;
        }
        VideoSortData::where('video_id', $video->id)->delete();
        $this->compact($previousCategoryId);
        $this->createFor($video);
    }

    public function deleteFor(Video $video): void
    {
        $categoryIds = VideoSortData::where('video_id', $video->id)->pluck('category_id')->unique();
        VideoSortData::where('video_id', $video->id)->delete();
        foreach ($categoryIds as $categoryId) {
            $this->compact((int) $categoryId);
        }
    }

    public function deleteForCategory(Category $category): void
    {
        VideoSortData::where('category_id', $category->id)->delete();
    }

    public function reorder(Category $category, array $videoIds): void
    {
        Category::query()->lockForUpdate()->findOrFail($category->id);
        VideoSortData::whereIn('video_id', $videoIds)->delete();
        foreach ($videoIds as $position => $videoId) {
            $sort = VideoSortData::create([
                'video_id' => $videoId,
                'category_id' => $category->id,
                'category_order' => $category->sort_order,
                'video_order' => $position,
            ]);
            $this->syncChanges->recordVideoSort($sort);
        }
    }

    public function updateCategoryOrder(Category $category): void
    {
        $rows = VideoSortData::where('category_id', $category->id)->get();
        foreach ($rows as $sort) {
            if ($sort->category_order !== $category->sort_order) {
                $sort->update(['category_order' => $category->sort_order]);
                $this->syncChanges->recordVideoSort($sort);
            }
        }
    }

    public static function payload(VideoSortData $sort): array
    {
        return $sort->only('video_id', 'category_id', 'category_order', 'video_order');
    }

    public function repairAll(): void
    {
        $activeVideoIds = Video::query()->pluck('id');
        if ($activeVideoIds->isEmpty()) {
            VideoSortData::query()->delete();
        } else {
            VideoSortData::whereNotIn('video_id', $activeVideoIds)->delete();
        }

        foreach (Category::query()->orderBy('sort_order')->get() as $category) {
            $sortData = VideoSortData::query()
                ->selectRaw('video_id, MIN(video_order) as video_order')
                ->groupBy('video_id');
            $videoIds = Video::query()
                ->where('videos.category_id', $category->id)
                ->leftJoinSub($sortData, 'video_sort', 'video_sort.video_id', '=', 'videos.id')
                ->orderByRaw('COALESCE(video_sort.video_order, 2147483647)')
                ->orderBy('videos.id')
                ->pluck('videos.id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $this->reorder($category, $videoIds);
        }
    }

    private function compact(int $categoryId): void
    {
        $rows = VideoSortData::where('category_id', $categoryId)
            ->orderBy('video_order')->orderBy('id')->get();
        foreach ($rows as $position => $sort) {
            if ($sort->video_order !== $position) {
                $sort->update(['video_order' => $position]);
                $this->syncChanges->recordVideoSort($sort);
            }
        }
    }
}
