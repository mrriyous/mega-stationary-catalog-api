<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\SyncChange;
use App\Support\SyncPayload;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Alat Tulis', 'sort_order' => 0],
        ];
        foreach ($categories as $categoryData) {
            $category = Category::firstOrCreate(
                ['name' => $categoryData['name']],
                ['sort_order' => $categoryData['sort_order']],
            );
            if (! SyncChange::where('entity_type', 'category')->where('entity_id', $category->id)->exists()) {
                SyncChange::create([
                    'entity_type' => 'category',
                    'entity_id' => $category->id,
                    'action' => 'upsert',
                    'payload' => SyncPayload::category($category),
                ]);
            }
        }
    }
}
