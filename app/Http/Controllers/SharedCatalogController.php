<?php

namespace App\Http\Controllers;

use App\Models\CatalogShareLink;
use App\Models\Category;
use App\Models\Video;
use App\Models\VideoSortData;
use App\Services\MediaStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SharedCatalogController extends Controller
{
    public function __construct(private readonly MediaStorageService $mediaStorage) {}

    public function show(string $token)
    {
        $share = $this->resolve($token);
        $categories = collect();
        if (! $share->category_id) {
            $categories = Category::query()->orderBy('sort_order')->get();
        }
        $selectedCategory = $share->category;
        $selectedCategoryName = $selectedCategory?->name ?? 'Semua kategori';

        return view('shared.catalog', [
            'share' => $share,
            'token' => $token,
            'categories' => $categories,
            'selectedCategoryName' => $selectedCategoryName,
        ]);
    }

    public function videos(Request $request, string $token): JsonResponse
    {
        $share = $this->resolve($token);
        $categoryId = $share->category_id;
        if (! $categoryId) {
            $requestedCategoryId = $request->integer('category_id');
            if ($requestedCategoryId) {
                $categoryId = $requestedCategoryId;
            }
        }
        $videos = $this->query($share, $categoryId)
            ->paginate(min(max($request->integer('per_page', 24), 1), 48));
        $data = collect($videos->items())->map(fn (Video $video) => $this->videoData($video, $share, $token));
        $meta = [
            'current_page' => $videos->currentPage(),
            'last_page' => $videos->lastPage(),
        ];

        return response()->json(['data' => $data, 'meta' => $meta]);
    }

    public function detail(string $token, Video $video)
    {
        $share = $this->resolve($token);
        abort_unless($this->query($share)->where('videos.id', $video->id)->exists(), 404);

        $price = $share->price_type === 'wholesale' ? $video->wholesale_price : $video->normal_price;
        $description = $video->description ?: 'Tidak ada deskripsi.';

        return view('shared.video', [
            'share' => $share,
            'token' => $token,
            'video' => $video,
            'price' => $price,
            'description' => $description,
        ]);
    }

    public function cover(string $token, Video $video): Response
    {
        $share = $this->resolve($token);
        abort_unless($this->query($share)->where('videos.id', $video->id)->exists(), 404);
        abort_unless($video->cover_path, 404);

        return $this->mediaStorage->response($video->cover_path, basename($video->cover_path));
    }

    public function media(string $token, Video $video): Response
    {
        $share = $this->resolve($token);
        abort_unless($this->query($share)->where('videos.id', $video->id)->exists(), 404);

        return $this->mediaStorage->response($video->video_path, basename($video->video_path));
    }

    private function resolve(string $token): CatalogShareLink
    {
        $share = CatalogShareLink::query()->where('token_hash', hash('sha256', $token))->first();
        abort_unless($share && $share->expires_at->isFuture() && $share->user && ! $share->user->trashed(), 410);

        return $share;
    }

    private function query(CatalogShareLink $share, ?int $categoryId = null): Builder
    {
        $sort = VideoSortData::query()
            ->selectRaw('video_id, MIN(category_order) category_order, MIN(video_order) video_order')
            ->groupBy('video_id');

        return Video::query()
            ->leftJoinSub($sort, 'video_sort', 'video_sort.video_id', '=', 'videos.id')
            ->select('videos.*')
            ->when($share->category_id, fn ($query, $id) => $query->where('videos.category_id', $id))
            ->when(! $share->category_id && $categoryId, fn ($query) => $query->where('videos.category_id', $categoryId))
            ->when($share->search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('videos.product_code', 'like', "%{$search}%")
                        ->orWhere('videos.product_name', 'like', "%{$search}%")
                        ->orWhere('videos.description', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('COALESCE(video_sort.category_order, 2147483647)')
            ->orderByRaw('COALESCE(video_sort.video_order, 2147483647)')
            ->orderBy('videos.id');
    }

    private function videoData(Video $video, CatalogShareLink $share, string $token): array
    {
        $price = $share->price_type === 'wholesale' ? $video->wholesale_price : $video->normal_price;
        $coverUrl = $video->cover_path ? route('shared-catalog.cover', [$token, $video]) : null;
        $previewUrl = route('shared-catalog.media', [$token, $video]).'#t=0.5';
        $detailUrl = route('shared-catalog.video', [$token, $video]);

        return [
            'id' => $video->id,
            'code' => $video->product_code,
            'name' => $video->product_name,
            'description' => $video->description,
            'price' => $price,
            'cover_url' => $coverUrl,
            'preview_url' => $previewUrl,
            'detail_url' => $detailUrl,
        ];
    }
}
