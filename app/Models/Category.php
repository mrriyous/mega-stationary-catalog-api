<?php

namespace App\Models;

use App\Traits\AppActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'sort_order'])]
class Category extends Model
{
    use AppActivityLog, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }
}
