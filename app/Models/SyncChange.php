<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['entity_type', 'entity_id', 'action', 'payload'])]
class SyncChange extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'payload' => 'array',
        ];
    }
}
