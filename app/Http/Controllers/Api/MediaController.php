<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Services\MediaStorageService;
use App\Services\SyncChangeService;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaStorageService $media,
        private readonly SyncChangeService $syncChanges,
    ) {}

    public function video(Video $video): Response
    {
        $this->ensureAvailable($video, 'video');
        $filename = $video->product_code.'.'.pathinfo($video->video_path, PATHINFO_EXTENSION);

        return $this->media->response($video->video_path, $filename, true);
    }

    public function cover(Video $video): Response
    {
        abort_unless($video->cover_path, 404);
        $this->ensureAvailable($video, 'cover');

        return $this->media->response($video->cover_path, basename($video->cover_path));
    }

    private function ensureAvailable(Video $video, string $type): void
    {
        $path = $type === 'video' ? $video->video_path : $video->cover_path;
        $column = $type.'_file_available';
        $available = $path !== null && $this->media->exists($path);
        if ($video->{$column} !== $available) {
            $video->timestamps = false;
            $video->update([$column => $available]);
            $video->timestamps = true;
            $this->syncChanges->recordVideo($video->fresh());
        }
        abort_unless($available, 404, 'Media file is not available.');
    }
}
