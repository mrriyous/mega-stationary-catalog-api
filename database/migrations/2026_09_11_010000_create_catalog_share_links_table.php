<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'user')
            ->where('normal_price_access', true)
            ->where('wholesale_price_access', true)
            ->update(['wholesale_price_access' => false]);

        Schema::create('catalog_share_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('token_hash', 64)->unique();
            $table->string('price_type', 20);
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('search', 255)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_share_links');
    }
};
