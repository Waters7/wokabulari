<?php

use App\Actions\Dorm\ConfirmDormAttendance;
use App\Http\Requests\SettingsRequest;
use App\Jobs\CreateBackup;
use App\Services\SettingService;
use App\Services\System\BackupService;
use App\Services\System\StorageUsage;
use Flux\Flux;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan sistem')] class extends Component {
    /** Nilai disimpan bersarang (attendance → late_after) agar cocok dengan wire:model. */
    public array $values = [];

    public function mount(SettingService $settings): void
    {
        $this->values = Arr::undot(Arr::only($settings->all(), array_keys(config('wokabulari.settings'))));
    }

    public function save(SettingService $settings, ConfirmDormAttendance $confirm): void
    {
        $wasAuto = (bool) $settings->get('dorm.auto_confirm');
        $flat = Arr::dot($this->values);
        // Array (jenis izin) jangan dipecah per indeks.
        $flat = collect($flat)->reject(fn ($v, $k) => str_starts_with($k, 'exit.guardian_required_types.'))->all();
        $flat['exit.guardian_required_types'] = array_values($this->values['exit']['guardian_required_types'] ?? []);

        $this->resetErrorBag();
        $validator = Validator::make($this->values, SettingsRequest::rulesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ["values.{$k}" => $m])->all());
        }

        foreach (['session.validate_location', 'session.documentation_required', 'dorm.notify_guardian_absent', 'dorm.auto_confirm', 'leader.weekly_email'] as $bool) {
            $flat[$bool] = (bool) ($flat[$bool] ?? false);
        }
        foreach (['attendance.default_radius_m', 'attendance.max_gps_accuracy_m', 'session.code_duration_minutes', 'exit.max_per_week', 'exit.min_hours_before', 'files.max_upload_mb', 'retention.selfie_days_after_batch'] as $int) {
            $flat[$int] = (int) $flat[$int];
        }
        foreach (['attendance.face_threshold', 'grade.threshold_a', 'grade.threshold_b', 'grade.threshold_c'] as $float) {
            $flat[$float] = (float) $flat[$float];
        }

        $settings->setMany(Arr::only($flat, array_keys(config('wokabulari.settings'))));

        // Baru dinyalakan: absen mandiri yang masih menunggu ikut dikonfirmasi.
        $confirmed = ! $wasAuto && $flat['dorm.auto_confirm'] ? $confirm->autoConfirmPending() : 0;
        if ($wasAuto !== $flat['dorm.auto_confirm']) {
            activity('asrama')->causedBy(auth()->user())->withProperties(['otomatis' => $flat['dorm.auto_confirm'], 'dikonfirmasi' => $confirmed])
                ->log($flat['dorm.auto_confirm'] ? 'Menyalakan konfirmasi otomatis absen asrama' : 'Mematikan konfirmasi otomatis absen asrama');
        }

        Flux::toast(variant: 'success', text: 'Pengaturan tersimpan.'.($confirmed ? ' '.$confirmed.' absen asrama yang menunggu ikut dikonfirmasi otomatis.' : ''));
    }

    public function refreshStorage(StorageUsage $usage): void
    {
        $usage->summary(fresh: true);
        Flux::toast(text: 'Pemakaian penyimpanan diperbarui.');
    }

    /** Cadangan dibuat di antrean; hasilnya muncul di daftar & notifikasi. */
    public function backupNow(BackupService $backups): void
    {
        abort_unless(auth()->user()->can('setting.manage'), 403);

        if ($backups->busy()) {
            Flux::toast(variant: 'warning', text: 'Cadangan sedang dibuat. Tunggu sampai selesai.');

            return;
        }

        $token = (string) Str::uuid();
        $backups->setStatus(['state' => 'queued', 'token' => $token, 'queued_at' => now()->toIso8601String(), 'by' => auth()->user()->name]);
        CreateBackup::dispatch($token, auth()->id());

        Flux::toast(variant: 'success', text: 'Cadangan sedang dibuat. Ngana diberi tahu saat siap diunduh.');
    }

    public function deleteBackup(string $name, BackupService $backups): void
    {
        abort_unless(auth()->user()->can('setting.manage'), 403);

        try {
            $backups->delete($name);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        activity('sistem')->causedBy(auth()->user())->withProperties(['berkas' => $name])->log('Menghapus cadangan');
        app(StorageUsage::class)->summary(fresh: true);
        Flux::toast(text: 'Cadangan dihapus.');
    }

    public function with(StorageUsage $usage, BackupService $backups): array
    {
        return [
            'storage' => $usage->summary(),
            'backups' => $backups->list(),
            'backupStatus' => $backups->status(),
            'backupBusy' => $backups->busy(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Pengaturan sistem" subtitle="Nilai yang memengaruhi absen, sesi, asrama, penilaian, dan retensi data." />

    @unless (config('wokabulari.face_verification_enabled') && config('wokabulari.geofence_enabled'))
        <flux:callout icon="exclamation-triangle" color="red" class="mb-4 [overflow-wrap:anywhere]"
                      heading="Verifikasi wajah atau geofence sedang nonaktif"
                      text="FACE_VERIFICATION_ENABLED/GEOFENCE_ENABLED bernilai false di .env. Wajib true di produksi." />
    @endunless

    @php($disk = $storage['disk'])
    <x-card title="Penyimpanan & cadangan" class="mb-4" data-test="storage-panel">
        <div @if ($backupBusy) wire:poll.5s @endif class="space-y-4">
            @if ($disk)
                <div>
                    <div class="mb-1 flex flex-wrap items-baseline justify-between gap-2 text-sm">
                        <span class="font-semibold">Disk VPS</span>
                        <span class="tabular-nums text-hutan/70 dark:text-santan/70">
                            {{ StorageUsage::format($disk['used']) }} dari {{ StorageUsage::format($disk['total']) }} terpakai · sisa {{ StorageUsage::format($disk['free']) }}
                        </span>
                    </div>
                    @php($low = $disk['free'] / $disk['total'] * 100 < StorageUsage::LOW_FREE_PERCENT)
                    <div class="h-3 overflow-hidden rounded-full bg-zinc-100 dark:bg-hutan-800" role="progressbar" aria-valuenow="{{ $disk['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Pemakaian disk VPS">
                        <div @class(['h-full rounded-full', 'bg-rica' => $low, 'bg-woka' => ! $low]) style="width: {{ min(100, $disk['percent']) }}%"></div>
                    </div>
                    @if ($low)
                        <p class="mt-1 text-xs font-semibold text-rica">Sisa disk di bawah {{ StorageUsage::LOW_FREE_PERCENT }}%. Unduh lalu hapus cadangan lama, atau tambah kapasitas VPS.</p>
                    @endif
                </div>
            @else
                <p class="text-sm text-hutan/60 dark:text-santan/60">Kapasitas disk VPS tidak terbaca di server ini.</p>
            @endif

            <div>
                <p class="mb-1 text-sm font-semibold">Dipakai Wokabulari: {{ StorageUsage::format($storage['total']) }}
                    @if ($disk) <span class="font-normal text-hutan/60 dark:text-santan/60">({{ angka($storage['total'] / $disk['total'] * 100, 1) }}% disk)</span> @endif
                </p>
                <ul class="divide-y divide-zinc-100 text-sm dark:divide-hutan-800">
                    @foreach ($storage['parts'] as $key => $part)
                        <li class="flex items-center justify-between gap-3 py-1.5" wire:key="sp-{{ $key }}">
                            <span class="min-w-0 text-hutan/70 dark:text-santan/70">{{ $part['label'] }}</span>
                            <span class="shrink-0 font-semibold tabular-nums">{{ StorageUsage::format($part['bytes']) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-1 text-xs text-hutan/50 dark:text-santan/50">Diukur {{ tanggal_jam(\Illuminate\Support\Carbon::parse($storage['measured_at'])) }} WITA. Image Docker & cadangan harian di luar aplikasi (/opt/backup) tidak termasuk.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" icon="arrow-path" wire:click="refreshStorage">Perbarui</flux:button>
                <flux:button size="sm" variant="primary" icon="archive-box-arrow-down" wire:click="backupNow" :disabled="$backupBusy">
                    {{ $backupBusy ? 'Cadangan sedang dibuat…' : 'Buat cadangan sekarang' }}
                </flux:button>
            </div>

            @if (($backupStatus['state'] ?? null) === 'failed')
                <flux:callout icon="exclamation-triangle" color="red" :text="'Cadangan terakhir gagal: '.($backupStatus['message'] ?? '')" />
            @endif

            <div>
                <p class="mb-1 text-sm font-semibold">Cadangan tersimpan (maks. {{ BackupService::KEEP }} terbaru)</p>
                @forelse ($backups as $backup)
                    <div class="flex items-center justify-between gap-2 border-t border-zinc-100 py-2 text-sm first:border-0 dark:border-hutan-800" wire:key="bk-{{ $backup['name'] }}">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $backup['name'] }}</p>
                            <p class="text-xs text-hutan/60 dark:text-santan/60">{{ tanggal_jam($backup['created_at']) }} · {{ StorageUsage::format($backup['size']) }}</p>
                        </div>
                        <div class="flex shrink-0">
                            <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('system.backups.download', $backup['name'])" aria-label="Unduh" />
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteBackup('{{ $backup['name'] }}')" wire:confirm="Hapus cadangan ini?" aria-label="Hapus" />
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-hutan/60 dark:text-santan/60">Belum ada cadangan dari aplikasi.</p>
                @endforelse
                <p class="mt-2 text-xs text-hutan/50 dark:text-santan/50">Berisi database & berkas pribadi peserta. Unduh dan simpan di luar server (mis. drive terenkripsi); cadangan di server yang sama bukan cadangan sesungguhnya.</p>
            </div>
        </div>
    </x-card>

    <form wire:submit="save" class="space-y-4">
        <div class="grid gap-4 lg:grid-cols-2">
            <x-card title="Absen harian">
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="values.attendance.default_radius_m" type="number" label="Radius bawaan (m)" />
                    <flux:input wire:model="values.attendance.max_gps_accuracy_m" type="number" label="Akurasi GPS maks. (m)" />
                    <flux:input wire:model="values.attendance.face_threshold" type="number" step="0.01" label="Ambang kemiripan wajah" description="Jarak Euclidean; makin kecil makin ketat (bawaan 0,5)." />
                    <flux:input wire:model="values.attendance.open_from" type="time" label="Absen dibuka (bawaan)" description="Dipakai bila angkatan belum mengatur jadwal absennya sendiri (Master → Angkatan)." />
                    <flux:input wire:model="values.attendance.late_after" type="time" label="Batas terlambat (bawaan)" />
                </div>
            </x-card>

            <x-card title="Sesi & kode kehadiran">
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="values.session.code_duration_minutes" type="number" label="Durasi kode (menit)" />
                    <flux:switch wire:model="values.session.validate_location" label="Validasi lokasi saat input kode" />
                    <flux:switch wire:model="values.session.documentation_required" label="Wajib dokumentasi kelas" description="Pengajar mengambil foto kamera atau mengunggah file dokumentasi saat mulai dan akhir sesi." />
                    <flux:input wire:model="values.session.documentation_from" type="date" label="Wajib dokumentasi mulai tanggal" description="Berlaku untuk sesi pada tanggal ini dan seterusnya." />
                </div>
            </x-card>

            <x-card title="Asrama">
                <div class="grid gap-3 sm:grid-cols-3">
                    <flux:input wire:model="values.dorm.check_window_start" type="time" label="Absen malam mulai" />
                    <flux:input wire:model="values.dorm.check_window_end" type="time" label="Absen malam selesai" />
                    <flux:input wire:model="values.dorm.curfew" type="time" label="Jam malam" />
                    <flux:input wire:model="values.dorm.late_until" type="time" label="Absen terlambat s.d." description="Setelah jendela absen, absen masih bisa sampai jam ini dan tercatat terlambat." />
                </div>
                <flux:switch class="mt-3" wire:model="values.dorm.notify_guardian_absent" label="Kabari wali bila anak belum absen asrama" />
                <flux:switch class="mt-3" wire:model="values.dorm.auto_confirm" label="Konfirmasi otomatis absen asrama"
                             description="Aktif: absen malam mandiri yang lolos verifikasi wajah & lokasi langsung terkonfirmasi. Mati: pengelola asrama mengonfirmasi manual." />
            </x-card>

            <x-card title="Izin keluar asrama">
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="values.exit.max_per_week" type="number" label="Maks. pengajuan per minggu" />
                    <flux:input wire:model="values.exit.min_hours_before" type="number" label="Minimal jam sebelum keluar" />
                </div>
                <flux:checkbox.group class="mt-3" wire:model="values.exit.guardian_required_types" label="Wajib persetujuan wali">
                    <flux:checkbox value="temporary" label="Keluar sementara" />
                    <flux:checkbox value="home_visit" label="Pulang ke rumah" />
                    <flux:checkbox value="overnight" label="Menginap" />
                </flux:checkbox.group>
            </x-card>

            <x-card title="Penilaian">
                <div class="grid grid-cols-3 gap-3">
                    <flux:input wire:model="values.grade.threshold_a" type="number" label="A ≥" />
                    <flux:input wire:model="values.grade.threshold_b" type="number" label="B ≥" />
                    <flux:input wire:model="values.grade.threshold_c" type="number" label="C ≥" />
                </div>
                <flux:text class="mt-2 text-xs">Di bawah batas C mendapat predikat D.</flux:text>
            </x-card>

            <x-card title="Berkas, retensi & program">
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="values.files.max_upload_mb" type="number" label="Batas unggah (MB)" />
                    <flux:input wire:model="values.retention.selfie_days_after_batch" type="number" label="Hapus selfie (hari setelah angkatan)" />
                    <flux:input wire:model="values.program.name" label="Nama program" class="sm:col-span-2" />
                    <flux:input wire:model="values.program.organizer" label="Penyelenggara" class="sm:col-span-2" />
                    <flux:input wire:model="values.program.dorm_contact" label="Kontak pengelola asrama" type="tel" />
                </div>
                <flux:switch class="mt-3" wire:model="values.leader.weekly_email" label="Email ringkasan mingguan ke pimpinan" />
            </x-card>
        </div>

        <div class="sticky bottom-20 flex justify-end lg:bottom-4">
            <flux:button type="submit" variant="primary" icon="check">Simpan pengaturan</flux:button>
        </div>
    </form>
</div>
