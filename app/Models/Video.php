<?php

namespace App\Models;

use App\Traits\AppActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id',
    'product_code',
    'product_name',
    'description',
    'normal_price',
    'wholesale_price',
    'video_path',
    'cover_path',
    'video_size_bytes',
    'video_file_available',
    'cover_file_available',
])]
class Video extends Model
{
    use AppActivityLog, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'video_size_bytes' => 'integer',
            'video_file_available' => 'boolean',
            'cover_file_available' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
