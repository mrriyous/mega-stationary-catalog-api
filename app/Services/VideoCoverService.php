<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class VideoCoverService
{
    public function generate(string $videoPath): string
    {
        $coverPath = 'covers/'.Str::uuid().'.jpg';
        $absoluteCoverPath = Storage::path($coverPath);
        $directory = dirname($absoluteCoverPath);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the video cover directory.');
        }

        $process = new Process([
            $this->binary(),
            '-hide_banner', '-loglevel', 'error', '-y',
            '-ss', '0.5', '-i', Storage::path($videoPath),
            '-frames:v', '1', '-vf', 'scale=720:-2', '-q:v', '3',
            $absoluteCoverPath,
        ]);
        $process->setTimeout(60);
        $process->run();
        if (! $process->isSuccessful() || ! is_file($absoluteCoverPath) || filesize($absoluteCoverPath) === 0) {
            Storage::delete($coverPath);
            throw new RuntimeException('FFmpeg could not generate the video cover: '.$process->getErrorOutput());
        }

        return $coverPath;
    }

    private function binary(): string
    {
        $configured = (string) config('media.ffmpeg_binary', 'ffmpeg');
        if ($configured !== 'ffmpeg') {
            return $configured;
        }
        foreach (['/opt/homebrew/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/usr/bin/ffmpeg'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return 'ffmpeg';
    }
}
