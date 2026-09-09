<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncChange extends Model
{
    protected $fillable = ['entity_type', 'entity_id', 'action', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
