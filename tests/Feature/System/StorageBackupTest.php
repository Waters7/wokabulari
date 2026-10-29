<?php

use App\Jobs\CreateBackup;
use App\Notifications\BackupFinishedNotification;
use App\Services\System\BackupService;
use App\Services\System\StorageUsage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('private');
    Storage::fake('public');
    Storage::fake('backups');
    Storage::disk('private')->put('signatures/2026/11/ttd.png', str_repeat('x', 2048));
    Storage::disk('private')->put('livewire-tmp/sementara.jpg', 'tmp');
    Storage::disk('public')->put('avatars/foto.jpg', str_repeat('y', 1024));
});

test('pengaturan sistem menampilkan pemakaian penyimpanan Wokabulari dan disk VPS', function () {
    $summary = app(StorageUsage::class)->summary(fresh: true);
    expect($summary['parts']['private']['bytes'])->toBe(2048 + 3)
        ->and($summary['parts']['public']['bytes'])->toBe(1024)
        ->and($summary['disk'])->not->toBeNull()
        ->and($summary['disk']['total'])->toBeGreaterThan($summary['disk']['free']);

    Livewire::actingAs(superAdmin())->test('pages::system.settings')
        ->assertSee('Penyimpanan & cadangan')->assertSee('Disk VPS')->assertSee('Dipakai Wokabulari')
        ->assertSee('Berkas privat')->assertSee('Belum ada cadangan dari aplikasi');

    expect(StorageUsage::format(1536))->toBe('2 KB')->and(StorageUsage::format(1610612736))->toBe('1,5 GB');
});

test('tombol cadangan membuat ZIP berisi database & berkas, lalu memberi tahu peminta', function () {
    Notification::fake();
    $admin = superAdmin();

    Livewire::actingAs($admin)->test('pages::system.settings')->call('backupNow');

    $backups = app(BackupService::class)->list();
    expect($backups)->toHaveCount(1)
        ->and(app(BackupService::class)->status())->state->toBe('done')->file->toBe($backups[0]['name']);
    Notification::assertSentTo($admin, BackupFinishedNotification::class, fn ($n) => $n->success);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('backups')->path($backups[0]['name']));
    $sql = $zip->getFromName('database.sql');
    expect($sql)->toContain('CREATE TABLE')->toContain('INSERT INTO `users`')->toContain($admin->email)
        ->and($zip->locateName('storage/private/signatures/2026/11/ttd.png'))->not->toBeFalse()
        ->and($zip->locateName('storage/public/avatars/foto.jpg'))->not->toBeFalse()
        ->and($zip->locateName('storage/private/livewire-tmp/sementara.jpg'))->toBeFalse()
        ->and($zip->getFromName('BACA-SAYA.txt'))->toContain('Pemulihan');
    $zip->close();

    // Unduh hanya untuk pengelola pengaturan sistem; nama berkas divalidasi.
    $this->actingAs($admin)->get(route('system.backups.download', $backups[0]['name']))->assertOk()->assertDownload($backups[0]['name']);
    $this->actingAs(programAdmin())->get(route('system.backups.download', $backups[0]['name']))->assertForbidden();
    $this->actingAs($admin)->get('/sistem/cadangan/wokabulari-20260101-000000.zip')->assertNotFound();
    $this->actingAs($admin)->get('/sistem/cadangan/..%2F.env')->assertNotFound();
});

test('cadangan dibatasi 5 terbaru, bisa dihapus, dan tidak dobel saat masih berjalan', function () {
    foreach (range(1, 6) as $i) {
        Storage::disk('backups')->put(sprintf('wokabulari-202611%02d-010000.zip', $i), 'zip');
    }

    expect(app(BackupService::class)->prune())->toBe(1)
        ->and(app(BackupService::class)->list()->pluck('name')->last())->toBe('wokabulari-20261102-010000.zip');

    Livewire::actingAs(superAdmin())->test('pages::system.settings')->call('deleteBackup', 'wokabulari-20261106-010000.zip');
    expect(Storage::disk('backups')->exists('wokabulari-20261106-010000.zip'))->toBeFalse();

    Queue::fake();
    Livewire::actingAs(superAdmin())->test('pages::system.settings')->call('backupNow')->call('backupNow')
        ->assertSee('Cadangan sedang dibuat…');
    Queue::assertPushedTimes(CreateBackup::class, 1);
});
