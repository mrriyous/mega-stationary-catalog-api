<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'product_code', 'product_name', 'normal_price',
        'wholesale_price', 'video_path', 'cover_path', 'video_size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'video_size_bytes' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
