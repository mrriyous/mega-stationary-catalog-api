<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class VideoCoverService
{
    public function __construct(private readonly MediaStorageService $media) {}

    public function generate(string $videoPath): string
    {
        $coverPath = 'covers/'.Str::uuid().'.jpg';
        $directory = sys_get_temp_dir().'/ms-covers-'.Str::uuid();
        $absoluteVideoPath = $directory.'/source.'.(pathinfo($videoPath, PATHINFO_EXTENSION) ?: 'mp4');
        $absoluteCoverPath = $directory.'/cover.jpg';

        try {
            if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException('Unable to create a temporary video cover directory.');
            }
            $source = $this->media->disk()->readStream($videoPath);
            $destination = fopen($absoluteVideoPath, 'wb');
            if (! is_resource($source) || ! is_resource($destination)) {
                throw new RuntimeException('Unable to read the source video.');
            }
            try {
                stream_copy_to_stream($source, $destination);
            } finally {
                fclose($source);
                fclose($destination);
            }

            $process = new Process([
                $this->binary(),
                '-hide_banner', '-loglevel', 'error', '-y',
                '-ss', '0.5', '-i', $absoluteVideoPath,
                '-frames:v', '1', '-vf', 'scale=720:-2', '-q:v', '3',
                $absoluteCoverPath,
            ]);
            $process->setTimeout(60);
            $process->run();
            if (! $process->isSuccessful() || ! is_file($absoluteCoverPath) || filesize($absoluteCoverPath) === 0) {
                throw new RuntimeException('FFmpeg could not generate the video cover: '.$process->getErrorOutput());
            }

            $cover = fopen($absoluteCoverPath, 'rb');
            if (! is_resource($cover)) {
                throw new RuntimeException('Unable to store the generated video cover.');
            }
            try {
                if (! $this->media->disk()->put($coverPath, $cover, ['visibility' => 'private'])) {
                    throw new RuntimeException('Unable to store the generated video cover.');
                }
            } finally {
                fclose($cover);
            }
        } finally {
            @unlink($absoluteVideoPath);
            @unlink($absoluteCoverPath);
            @rmdir($directory);
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
