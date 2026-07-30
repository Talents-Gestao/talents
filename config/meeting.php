<?php

declare(strict_types=1);

return [
    'max_upload_mb' => (int) env('MEETING_MAX_UPLOAD_MB', 500),
    'keep_audio' => (bool) env('MEETING_KEEP_AUDIO', true),
    'segment_seconds' => (int) env('MEETING_SEGMENT_SECONDS', 600),
    'whisper_timeout' => (int) env('MEETING_WHISPER_TIMEOUT', 300),
    'minutes_timeout' => (int) env('MEETING_MINUTES_TIMEOUT', 180),
];
