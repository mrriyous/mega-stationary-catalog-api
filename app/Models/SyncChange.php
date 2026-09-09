<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyncChange extends Model
{
    use SoftDeletes;

    protected $fillable = ['entity_type', 'entity_id', 'action', 'payload'];

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'payload' => 'array',
        ];
    }
}
