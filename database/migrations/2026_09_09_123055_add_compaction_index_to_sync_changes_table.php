<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sync_changes', function (Blueprint $table) {
            $table->index(
                ['entity_type', 'entity_id', 'id'],
                'sync_changes_compaction_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_changes', function (Blueprint $table) {
            $table->dropIndex('sync_changes_compaction_index');
        });
    }
};
