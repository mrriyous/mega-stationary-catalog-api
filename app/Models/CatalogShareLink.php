<?php

namespace App\Models;

use App\Traits\AppActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'token_hash', 'price_type', 'category_id', 'search', 'expires_at'])]
#[Hidden(['token_hash'])]
class CatalogShareLink extends Model
{
    use AppActivityLog;

    /**
     * @return list<string>
     */
    protected function activityLogExcept(): array
    {
        return ['token_hash'];
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'category_id' => 'integer', 'user_id' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }
}
