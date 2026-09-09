<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Video;

final class SyncPayload
{
    public static function category(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'sort_order' => $category->sort_order,
            'updated_at' => $category->updated_at?->toISOString(),
        ];
    }

    public static function video(Video $video): array
    {
        return [
            'id' => $video->id,
            'category_id' => $video->category_id,
            'product_code' => $video->product_code,
            'product_name' => $video->product_name,
            'normal_price' => $video->normal_price,
            'wholesale_price' => $video->wholesale_price,
            'video_size_bytes' => $video->video_size_bytes,
            'video_extension' => pathinfo($video->video_path, PATHINFO_EXTENSION),
            'cover_extension' => $video->cover_path ? pathinfo($video->cover_path, PATHINFO_EXTENSION) : null,
            'video_url' => route('videos.download', $video, false),
            'cover_url' => $video->cover_path
                ? route('videos.cover', $video, false)
                : null,
            'updated_at' => $video->updated_at?->toISOString(),
        ];
    }
}
