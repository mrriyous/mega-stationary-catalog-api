<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });

        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $username = match ($user->email) {
                'admin@mega.test' => 'admin',
                'user@mega.test' => 'user',
                default => 'user_'.$user->id,
            };
            DB::table('users')->where('id', $user->id)->update([
                'username' => $username,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_username_unique');
            $table->dropColumn('username');
        });
    }
};
