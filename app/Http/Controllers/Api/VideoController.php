<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncChange;
use App\Models\Video;
use App\Support\SyncPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VideoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $videos = Video::query()
            ->when($request->integer('category_id'), fn ($query, $id) => $query->where('category_id', $id))
            ->when($request->string('search')->trim()->toString(), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('product_code', 'like', "%{$search}%")
                        ->orWhere('product_name', 'like', "%{$search}%");
                });
            })
            ->latest('updated_at')
            ->paginate(min($request->integer('per_page', 24), 100));

        return response()->json([
            'data' => collect($videos->items())->map(fn (Video $video) => SyncPayload::video($video)),
            'meta' => [
                'current_page' => $videos->currentPage(),
                'last_page' => $videos->lastPage(),
                'total' => $videos->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->validated($request);
        $videoFile = $request->file('video');
        $coverFile = $request->file('cover');
        $videoPath = $videoFile->store('videos');
        $coverPath = $coverFile?->store('covers');

        try {
            $video = DB::transaction(function () use ($data, $videoFile, $videoPath, $coverPath) {
                $video = Video::create([
                    ...$data,
                    'video_path' => $videoPath,
                    'cover_path' => $coverPath,
                    'video_size_bytes' => $videoFile->getSize(),
                ]);
                $this->record($video);

                return $video;
            });
        } catch (\Throwable $error) {
            Storage::delete(array_filter([$videoPath, $coverPath]));
            throw $error;
        }

        return response()->json(['data' => SyncPayload::video($video)], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Video $video): JsonResponse
    {
        return response()->json(['data' => SyncPayload::video($video)]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Video $video): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->validated($request, $video);
        $newVideo = $request->file('video');
        $newCover = $request->file('cover');
        $removeCover = (bool) ($data['remove_cover'] ?? false);
        unset($data['remove_cover']);
        $newVideoPath = $newVideo?->store('videos');
        $newCoverPath = $newCover?->store('covers');
        $oldVideoPath = $video->video_path;
        $oldCoverPath = $video->cover_path;

        try {
            DB::transaction(function () use ($video, $data, $newVideo, $newVideoPath, $newCoverPath, $removeCover) {
                $video->update([
                    ...$data,
                    'video_path' => $newVideoPath ?? $video->video_path,
                    'cover_path' => $removeCover ? null : ($newCoverPath ?? $video->cover_path),
                    'video_size_bytes' => $newVideo?->getSize() ?? $video->video_size_bytes,
                ]);
                $this->record($video->fresh());
            });
        } catch (\Throwable $error) {
            Storage::delete(array_filter([$newVideoPath, $newCoverPath]));
            throw $error;
        }
        if ($newVideoPath) {
            Storage::delete($oldVideoPath);
        }
        if (($newCoverPath || $removeCover) && $oldCoverPath) {
            Storage::delete($oldCoverPath);
        }

        return response()->json(['data' => SyncPayload::video($video->fresh())]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Video $video): JsonResponse
    {
        $this->authorizeAdmin($request);
        $id = $video->id;
        $paths = array_filter([$video->video_path, $video->cover_path]);
        DB::transaction(function () use ($video, $id) {
            $video->delete();
            SyncChange::create(['entity_type' => 'video', 'entity_id' => $id, 'action' => 'delete', 'payload' => ['id' => $id]]);
        });
        Storage::delete($paths);

        return response()->json(status: 204);
    }

    private function validated(Request $request, ?Video $video = null): array
    {
        return $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'product_code' => ['required', 'string', 'max:100', Rule::unique('videos')->ignore($video)],
            'product_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'normal_price' => ['required', 'string', 'max:100'],
            'wholesale_price' => ['required', 'string', 'max:100'],
            'video' => [$video ? 'nullable' : 'required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:512000'],
            'cover' => ['nullable', 'image', 'max:10240'],
            'remove_cover' => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, 'Admin access required.');
    }

    private function record(Video $video): void
    {
        SyncChange::create(['entity_type' => 'video', 'entity_id' => $video->id, 'action' => 'upsert', 'payload' => SyncPayload::video($video)]);
    }
}
