<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientErrorController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'errors' => ['required', 'array', 'min:1', 'max:20'],
            'errors.*.reference' => ['required', 'uuid'],
            'errors.*.type' => ['required', 'string', 'max:255'],
            'errors.*.message' => ['required', 'string', 'max:5000'],
            'errors.*.stack_trace' => ['nullable', 'string', 'max:50000'],
            'errors.*.context' => ['nullable', 'string', 'max:255'],
            'errors.*.occurred_at' => ['required', 'date'],
            'errors.*.platform' => ['required', 'string', 'max:100'],
        ]);

        $accepted = [];
        foreach ($data['errors'] as $error) {
            ErrorLog::firstOrCreate(
                ['reference' => $error['reference']],
                [
                    'user_id' => $request->user()->getAuthIdentifier(),
                    'method' => 'CLIENT',
                    'path' => Str::limit($error['context'] ?? 'flutter', 65000, '…'),
                    'status_code' => 500,
                    'exception_class' => $error['type'],
                    'message' => $error['message'],
                    'trace' => $error['stack_trace'] ?? null,
                    'request_data' => [
                        'source' => 'flutter',
                        'platform' => $error['platform'],
                        'occurred_at' => $error['occurred_at'],
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 2000, '…'),
                ],
            );
            $accepted[] = $error['reference'];
        }

        return response()->json(['accepted' => $accepted], 202);
    }
}
