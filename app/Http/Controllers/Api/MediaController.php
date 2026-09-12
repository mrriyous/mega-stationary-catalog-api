<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Services\MediaStorageService;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    public function __construct(private readonly MediaStorageService $media) {}

    public function video(Video $video): Response
    {
        $filename = $video->product_code.'.'.pathinfo($video->video_path, PATHINFO_EXTENSION);

        return $this->media->response($video->video_path, $filename, true);
    }

    public function cover(Video $video): Response
    {
        abort_unless($video->cover_path, 404);

        return $this->media->response($video->cover_path, basename($video->cover_path));
    }
}
