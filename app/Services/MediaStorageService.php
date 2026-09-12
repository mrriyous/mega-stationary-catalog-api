<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class MediaStorageService
{
    public function disk(): FilesystemAdapter
    {
        return Storage::disk('s3');
    }

    public function delete(string|array|null $paths): void
    {
        $paths = array_values(array_filter((array) $paths));
        if ($paths !== []) {
            $this->disk()->delete($paths);
        }
    }

    public function response(string $path, string $filename, bool $download = false): RedirectResponse
    {
        abort_unless($this->disk()->exists($path), 404);

        $disposition = $download ? 'attachment' : 'inline';
        $url = $this->disk()->temporaryUrl(
            $path,
            now()->addMinutes((int) config('media.temporary_url_minutes', 15)),
            ['ResponseContentDisposition' => $disposition.'; filename="'.addslashes($filename).'"'],
        );

        return redirect()->away($url);
    }
}
