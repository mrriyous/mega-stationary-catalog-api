<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\MediaStorageService;
use App\Services\SyncChangeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AuditMediaAvailability extends Command
{
    protected $signature = 'media:audit';

    protected $description = 'Check every active video and cover against S3 and publish changed availability';

    public function handle(MediaStorageService $media, SyncChangeService $syncChanges): int
    {
        $checked = 0;
        $changed = 0;
        $missingVideos = 0;
        $missingCovers = 0;
        $failed = 0;

        Video::query()->orderBy('id')->each(function (Video $video) use (
            $media,
            $syncChanges,
            &$checked,
            &$changed,
            &$missingVideos,
            &$missingCovers,
            &$failed,
        ): void {
            try {
                $videoAvailable = $media->exists($video->video_path);
                $coverAvailable = $video->cover_path === null || $media->exists($video->cover_path);
                $checked++;
                if (! $videoAvailable) {
                    $missingVideos++;
                }
                if (! $coverAvailable) {
                    $missingCovers++;
                }
                if ($video->video_file_available === $videoAvailable
                    && $video->cover_file_available === $coverAvailable) {
                    return;
                }

                $video->timestamps = false;
                $video->update([
                    'video_file_available' => $videoAvailable,
                    'cover_file_available' => $coverAvailable,
                ]);
                $video->timestamps = true;
                $syncChanges->recordVideo($video->fresh());
                $changed++;
            } catch (\Throwable $error) {
                $failed++;
                $this->error("Video {$video->id}: storage check failed; availability was not changed.");
                Log::warning('Media availability audit failed.', [
                    'video_id' => $video->id,
                    'exception' => $error,
                ]);
            }
        });

        $this->info(
            "Checked {$checked}; changed {$changed}; missing videos {$missingVideos}; "
            ."missing covers {$missingCovers}; failed checks {$failed}.",
        );

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
