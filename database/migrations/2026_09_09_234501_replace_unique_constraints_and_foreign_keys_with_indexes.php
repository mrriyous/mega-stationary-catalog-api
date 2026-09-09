<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasForeignKey('videos', ['category_id'])) {
            Schema::table('videos', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
            });
        }

        $this->replaceUniqueWithIndex('users', 'email');
        $this->replaceUniqueWithIndex('users', 'username');
        $this->replaceUniqueWithIndex('categories', 'name');
        $this->replaceUniqueWithIndex('videos', 'product_code');

        $this->ensureIndex('videos', 'category_id');
    }

    public function down(): void
    {
        $this->replaceIndexWithUnique('users', 'email');
        $this->replaceIndexWithUnique('users', 'username');
        $this->replaceIndexWithUnique('categories', 'name');
        $this->replaceIndexWithUnique('videos', 'product_code');

        if (! Schema::hasForeignKey('videos', ['category_id'])) {
            Schema::table('videos', function (Blueprint $table) {
                $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
            });
        }
    }

    private function replaceUniqueWithIndex(string $table, string $column): void
    {
        if (Schema::hasIndex($table, [$column], 'unique')) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropUnique([$column]);
            });
        }

        $this->ensureIndex($table, $column);
    }

    private function replaceIndexWithUnique(string $table, string $column): void
    {
        if (Schema::hasIndex($table, [$column]) && ! Schema::hasIndex($table, [$column], 'unique')) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropIndex([$column]);
            });
        }

        if (! Schema::hasIndex($table, [$column], 'unique')) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->unique($column);
            });
        }
    }

    private function ensureIndex(string $table, string $column): void
    {
        if (! Schema::hasIndex($table, [$column])) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->index($column);
            });
        }
    }
};
