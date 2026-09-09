<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\SyncChange;
use App\Models\User;
use App\Support\SyncPayload;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@mega.test'], [
            'name' => 'Mega Admin', 'password' => 'password', 'role' => 'admin',
        ]);
        User::updateOrCreate(['email' => 'user@mega.test'], [
            'name' => 'Mega User', 'password' => 'password', 'role' => 'user',
        ]);

        foreach (['Informasi', 'Tutorial', 'Promo', 'Produk'] as $order => $name) {
            $category = Category::firstOrCreate(['name' => $name], ['sort_order' => $order]);
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
