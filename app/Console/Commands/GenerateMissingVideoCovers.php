<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\MediaStorageService;
use App\Services\SyncChangeService;
use App\Services\VideoCoverService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateMissingVideoCovers extends Command
{
    protected $signature = 'video-covers:generate {--force : Replace existing covers too}';

    protected $description = 'Generate server-side cover images from uploaded videos';

    public function handle(VideoCoverService $covers, SyncChangeService $syncChanges, MediaStorageService $media): int
    {
        $generated = 0;
        $failed = 0;
        $query = Video::query();
        if (! $this->option('force')) {
            $query->whereNull('cover_path');
        }

        $query->orderBy('id')->each(function (Video $video) use ($covers, $syncChanges, $media, &$generated, &$failed) {
            try {
                $oldCoverPath = $video->cover_path;
                $coverPath = $covers->generate($video->video_path);
                $video->timestamps = false;
                $video->update(['cover_path' => $coverPath]);
                $video->timestamps = true;
                $syncChanges->recordVideo($video->fresh());
                if ($oldCoverPath && $oldCoverPath !== $coverPath) {
                    $media->delete($oldCoverPath);
                }
                $generated++;
            } catch (\Throwable $error) {
                $failed++;
                $this->error("Video {$video->id}: {$error->getMessage()}");
                Log::warning('Failed to generate video cover.', ['video_id' => $video->id, 'exception' => $error]);
            }
        });

        $this->info("Generated {$generated} cover(s); {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
