<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CatalogShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CatalogShareController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $user = $request->user();
        $priceType = $user->wholesale_price_access && ! $user->normal_price_access ? 'wholesale' : 'normal';
        $token = Str::random(64);
        $search = trim((string) ($data['search'] ?? '')) ?: null;
        $share = CatalogShareLink::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'price_type' => $priceType,
            'category_id' => $data['category_id'] ?? null,
            'search' => $search,
            'expires_at' => now()->addHours(24),
        ]);

        $url = route('shared-catalog.show', $token);
        $expiresAt = $share->expires_at->toISOString();
        $category = $share->category?->name;

        return response()->json([
            'url' => $url,
            'expires_at' => $expiresAt,
            'category' => $category,
            'search' => $search,
        ], 201);
    }
}
