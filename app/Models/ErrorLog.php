<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'reference',
    'user_id',
    'method',
    'path',
    'status_code',
    'exception_class',
    'message',
    'trace',
    'request_data',
    'ip_address',
    'user_agent',
])]
class ErrorLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['request_data' => 'array'];
    }
}
