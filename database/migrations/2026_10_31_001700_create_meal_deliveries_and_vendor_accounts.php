<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Akun login vendor makanan terhubung ke data vendor.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('phone')->constrained()->nullOnDelete();
        });

        // Laporan pengantaran vendor untuk satu jadwal makan.
        Schema::create('meal_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_schedule_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('unit', 10);
            $table->text('menu');
            $table->timestamp('ready_at');
            $table->string('location', 150);
            $table->string('menu_photo_path')->nullable();
            $table->string('total_photo_path')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->index(['notified_at', 'ready_at']);
        });

        // Role & izin vendor ikut terpasang lewat migrasi agar server tidak perlu seeding ulang.
        $permission = Permission::findOrCreate('meal.deliver', 'web');
        Role::findOrCreate('vendor', 'web')->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_deliveries');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_id');
        });
    }
};
