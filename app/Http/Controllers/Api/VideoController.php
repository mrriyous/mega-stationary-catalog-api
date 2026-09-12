<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\VideoSortData;
use App\Services\MediaStorageService;
use App\Services\SyncChangeService;
use App\Services\VideoCoverService;
use App\Services\VideoSortService;
use App\Support\SyncPayload;
use App\Support\SystemErrorLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VideoController extends Controller
{
    public function __construct(
        private readonly VideoSortService $videoSorts,
        private readonly VideoCoverService $videoCovers,
        private readonly SyncChangeService $syncChanges,
        private readonly MediaStorageService $mediaStorage,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $sortData = VideoSortData::query()
            ->selectRaw('video_id, MIN(category_order) as category_order, MIN(video_order) as video_order')
            ->groupBy('video_id');
        $videos = Video::query()
            ->leftJoinSub($sortData, 'video_sort', 'video_sort.video_id', '=', 'videos.id')
            ->select('videos.*')
            ->when($request->integer('category_id'), fn ($query, $id) => $query->where('videos.category_id', $id))
            ->when($request->string('search')->trim()->toString(), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('videos.product_code', 'like', "%{$search}%")
                        ->orWhere('videos.product_name', 'like', "%{$search}%")
                        ->orWhere('videos.description', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('COALESCE(video_sort.category_order, 2147483647)')
            ->orderByRaw('COALESCE(video_sort.video_order, 2147483647)')
            ->orderBy('videos.id')
            ->paginate(min($request->integer('per_page', 24), 100));
        $data = collect($videos->items())->map(fn (Video $video) => SyncPayload::video($video, $request->user()));
        $meta = [
            'current_page' => $videos->currentPage(),
            'last_page' => $videos->lastPage(),
            'total' => $videos->total(),
        ];

        return response()->json(['data' => $data, 'meta' => $meta]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $videoFile = $request->file('video');
        $coverFile = $request->file('cover');
        $videoPath = $videoFile->store('videos', 's3');
        $coverPath = $coverFile?->store('covers', 's3');
        if (! $coverPath) {
            try {
                $coverPath = $this->videoCovers->generate($videoPath);
            } catch (\Throwable $error) {
                SystemErrorLogger::record($error, $request);
            }
        }
        try {
            $video = DB::transaction(function () use ($data, $videoFile, $videoPath, $coverPath) {
                $video = Video::create([
                    ...$data,
                    'video_path' => $videoPath,
                    'cover_path' => $coverPath,
                    'video_size_bytes' => $videoFile->getSize(),
                    'video_file_available' => true,
                    'cover_file_available' => $coverPath !== null,
                ]);
                $this->videoSorts->createFor($video);
                $this->syncChanges->recordVideo($video);

                return $video;
            });
        } catch (\Throwable $error) {
            $this->mediaStorage->delete([$videoPath, $coverPath]);
            throw $error;
        }

        $payload = SyncPayload::video($video);

        return response()->json(['data' => $payload], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Video $video): JsonResponse
    {
        $payload = SyncPayload::video($video, $request->user());

        return response()->json(['data' => $payload]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Video $video): JsonResponse
    {
        $data = $this->validated($request, $video);
        $newVideo = $request->file('video');
        $newCover = $request->file('cover');
        $removeCover = (bool) ($data['remove_cover'] ?? false);
        unset($data['remove_cover']);
        $newVideoPath = $newVideo?->store('videos', 's3');
        $newCoverPath = $newCover?->store('covers', 's3');
        $oldVideoPath = $video->video_path;
        $oldCoverPath = $video->cover_path;
        $previousCategoryId = (int) $video->category_id;
        if (! $newCoverPath && ($removeCover || ! $oldCoverPath)) {
            try {
                $newCoverPath = $this->videoCovers->generate($newVideoPath ?? $oldVideoPath);
                $removeCover = false;
            } catch (\Throwable $error) {
                SystemErrorLogger::record($error, $request);
            }
        }

        try {
            $video = DB::transaction(function () use ($video, $data, $newVideo, $newVideoPath, $newCoverPath, $removeCover, $previousCategoryId) {
                $videoPath = $newVideoPath ?? $video->video_path;
                $coverPath = $newCoverPath ?? $video->cover_path;
                if ($removeCover) {
                    $coverPath = null;
                }
                $videoSize = $newVideo?->getSize() ?? $video->video_size_bytes;

                $video->update([
                    ...$data,
                    'video_path' => $videoPath,
                    'cover_path' => $coverPath,
                    'video_size_bytes' => $videoSize,
                    'video_file_available' => $newVideoPath ? true : $video->video_file_available,
                    'cover_file_available' => $coverPath === null
                        ? true
                        : ($newCoverPath ? true : $video->cover_file_available),
                ]);
                $this->videoSorts->move($video, $previousCategoryId);
                $video = $video->fresh();
                $this->syncChanges->recordVideo($video);

                return $video;
            });
        } catch (\Throwable $error) {
            $this->mediaStorage->delete([$newVideoPath, $newCoverPath]);
            throw $error;
        }
        if ($newVideoPath) {
            $this->mediaStorage->delete($oldVideoPath);
        }
        if (($newCoverPath || $removeCover) && $oldCoverPath) {
            $this->mediaStorage->delete($oldCoverPath);
        }

        $payload = SyncPayload::video($video);

        return response()->json(['data' => $payload]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Video $video): JsonResponse
    {
        $id = $video->id;
        DB::transaction(function () use ($video, $id) {
            $this->videoSorts->deleteFor($video);
            $video->delete();
            $this->syncChanges->deleteVideo($id);
        });

        return response()->json(status: 204);
    }

    private function validated(Request $request, ?Video $video = null): array
    {
        return $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'product_code' => ['required', 'string', 'max:100'],
            'product_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'normal_price' => ['required', 'string', 'max:100'],
            'wholesale_price' => ['required', 'string', 'max:100'],
            'video' => [$video ? 'nullable' : 'required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:512000'],
            'cover' => ['nullable', 'image', 'max:10240'],
            'remove_cover' => ['nullable', 'boolean'],
        ]);
    }
}
