<?php

return [
    'temporary_url_minutes' => (int) env('MEDIA_TEMPORARY_URL_MINUTES', 15),
    'ffmpeg_binary' => env('FFMPEG_BINARY', 'ffmpeg'),
];
