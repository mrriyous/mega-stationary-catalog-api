<?php

namespace App\Support;

use App\Models\ErrorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class SystemErrorLogger
{
    /**
     * Persist diagnostic context and return the public reference identifier.
     */
    public static function record(Throwable $exception, Request $request, int $statusCode = 500): string
    {
        $reference = (string) Str::uuid();

        try {
            ErrorLog::create([
                'reference' => $reference,
                'user_id' => $request->user()?->getAuthIdentifier(),
                'method' => $request->method(),
                'path' => $request->path(),
                'status_code' => $statusCode,
                'exception_class' => $exception::class,
                'message' => Str::limit($exception->getMessage(), 65000, '…'),
                'trace' => Str::limit($exception->getTraceAsString(), 1000000, '…'),
                'request_data' => self::redact($request->input()),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 2000, '…'),
            ]);
        } catch (Throwable $loggingError) {
            Log::error('Failed to persist API system error.', [
                'reference' => $reference,
                'original_exception' => $exception,
                'logging_exception' => $loggingError,
            ]);
        }

        return $reference;
    }

    private static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (preg_match('/password|token|secret|authorization/i', (string) $key)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }
}
