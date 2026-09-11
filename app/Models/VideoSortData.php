<?php

namespace App\Models;

use App\Traits\AppActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['video_id', 'category_id', 'category_order', 'video_order'])]
class VideoSortData extends Model
{
    use AppActivityLog;

    protected $table = 'video_sort_data';

    protected function casts(): array
    {
        return [
            'video_id' => 'integer',
            'category_id' => 'integer',
            'category_order' => 'integer',
            'video_order' => 'integer',
        ];
    }
}
