<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Akun terhapus (soft delete) melepas NIS/email/HP agar bisa dipakai akun lain; nilai aslinya disimpan
 * di deleted_identity dan dikembalikan saat akun dipulihkan. Akun yang sudah terhapus ikut dirapikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('deleted_identity')->nullable()->after('deleted_at');
        });

        DB::table('users')->whereNotNull('deleted_at')->orderBy('id')->each(function (object $user) {
            $identity = array_filter(['username' => $user->username, 'email' => $user->email, 'phone' => $user->phone], fn ($value) => filled($value));

            if ($identity !== []) {
                DB::table('users')->where('id', $user->id)->update([
                    'deleted_identity' => json_encode($identity),
                    'username' => null, 'email' => null, 'phone' => null,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Kembalikan identitas akun terhapus bila belum dipakai akun lain.
        DB::table('users')->whereNotNull('deleted_identity')->orderBy('id')->each(function (object $user) {
            $identity = json_decode($user->deleted_identity, true) ?: [];
            $free = array_filter($identity, fn ($value, $field) => ! DB::table('users')->where($field, $value)->where('id', '!=', $user->id)->exists(), ARRAY_FILTER_USE_BOTH);

            DB::table('users')->where('id', $user->id)->update($free);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deleted_identity');
        });
    }
};
