<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    public function video(Video $video): BinaryFileResponse
    {
        abort_unless(Storage::exists($video->video_path), 404);
        $path = Storage::path($video->video_path);
        $filename = $video->product_code.'.'.pathinfo($video->video_path, PATHINFO_EXTENSION);

        return response()->download($path, $filename);
    }

    public function cover(Video $video): BinaryFileResponse
    {
        abort_unless($video->cover_path && Storage::exists($video->cover_path), 404);
        $path = Storage::path($video->cover_path);

        return response()->file($path);
    }
}
