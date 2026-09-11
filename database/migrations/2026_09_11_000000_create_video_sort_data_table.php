<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_sort_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_id')->index();
            $table->unsignedBigInteger('category_id');
            $table->unsignedInteger('category_order');
            $table->unsignedInteger('video_order');
            $table->timestamps();
            $table->index(['category_id', 'video_order']);
            $table->index(['category_order', 'video_order', 'video_id']);
        });

        $positions = [];
        $categories = DB::table('categories')->pluck('sort_order', 'id');
        $now = now();
        foreach (DB::table('videos')->whereNull('deleted_at')->orderBy('category_id')->orderBy('id')->get(['id', 'category_id']) as $video) {
            $position = $positions[$video->category_id] ?? 0;
            DB::table('video_sort_data')->insert([
                'video_id' => $video->id,
                'category_id' => $video->category_id,
                'category_order' => $categories[$video->category_id] ?? 0,
                'video_order' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('sync_changes')->insert([
                'entity_type' => 'video_sort',
                'entity_id' => $video->id,
                'action' => 'upsert',
                'payload' => json_encode([
                    'video_id' => $video->id,
                    'category_id' => $video->category_id,
                    'category_order' => $categories[$video->category_id] ?? 0,
                    'video_order' => $position,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $positions[$video->category_id] = $position + 1;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('video_sort_data');
    }
};
