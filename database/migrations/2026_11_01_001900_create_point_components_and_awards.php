<?php

use App\Enums\PointEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Master data Komponen Poin: aturan otomatis dikelompokkan per kategori, komponen tambahan bisa dibuat admin
 * atau diajukan pengajar (perlu persetujuan), lalu diberikan pengajar ke peserta kelasnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gamification_rules', function (Blueprint $table) {
            // Komponen tambahan tidak terikat event sistem.
            $table->string('event_key', 50)->nullable()->change();
            $table->string('category', 20)->default('other')->after('name');
            $table->boolean('is_custom')->default(false)->after('is_active');
            $table->string('status', 20)->default('approved')->after('is_custom');
            $table->foreignId('proposed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->after('proposed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('review_note')->nullable()->after('reviewed_at');
            $table->index(['is_custom', 'status']);
        });

        Schema::create('point_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gamification_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quiz_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('points');
            $table->string('note')->nullable();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['gamification_rule_id', 'created_at']);
        });

        // Kategori aturan otomatis yang sudah ada.
        foreach (PointEvent::cases() as $event) {
            DB::table('gamification_rules')->where('event_key', $event->value)->update(['category' => $event->category()->value]);
        }

        // Instalasi berjalan: tambah aturan baru "Isi tanda terima konsumsi" (instalasi baru lewat GamificationSeeder).
        if (DB::table('gamification_rules')->exists() && ! DB::table('gamification_rules')->where('event_key', PointEvent::MealReceipt->value)->exists()) {
            DB::table('gamification_rules')->insert([
                'event_key' => PointEvent::MealReceipt->value,
                'name' => PointEvent::MealReceipt->label(),
                'category' => PointEvent::MealReceipt->category()->value,
                'points' => PointEvent::MealReceipt->defaultPoints(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Izin memberi poin komponen tambahan: pengajar & admin program.
        $permission = Permission::findOrCreate('point.award', 'web');
        foreach (['teacher', 'program_admin', 'super_admin'] as $role) {
            Role::query()->where('name', $role)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('point_awards');
        DB::table('gamification_rules')->where('is_custom', true)->delete();

        Schema::table('gamification_rules', function (Blueprint $table) {
            $table->dropIndex(['is_custom', 'status']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('proposed_by');
            $table->dropColumn(['category', 'is_custom', 'status', 'reviewed_at', 'review_note']);
        });

        Permission::query()->where('name', 'point.award')->delete();
    }
};
