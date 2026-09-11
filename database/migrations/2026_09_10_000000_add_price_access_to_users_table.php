<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('normal_price_access')->default(true)->after('role');
            $table->boolean('wholesale_price_access')->default(false)->after('normal_price_access');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['normal_price_access', 'wholesale_price_access']);
        });
    }
};
