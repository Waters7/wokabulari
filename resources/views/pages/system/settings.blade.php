<?php

use App\Http\Requests\SettingsRequest;
use App\Services\SettingService;
use Flux\Flux;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
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

    public function save(SettingService $settings): void
    {
        $flat = Arr::dot($this->values);
        // Array (jenis izin) jangan dipecah per indeks.
        $flat = collect($flat)->reject(fn ($v, $k) => str_starts_with($k, 'exit.guardian_required_types.'))->all();
        $flat['exit.guardian_required_types'] = array_values($this->values['exit']['guardian_required_types'] ?? []);

        $this->resetErrorBag();
        $validator = Validator::make($this->values, SettingsRequest::rulesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ["values.{$k}" => $m])->all());
        }

        foreach (['session.validate_location', 'dorm.notify_guardian_absent', 'leader.weekly_email'] as $bool) {
            $flat[$bool] = (bool) ($flat[$bool] ?? false);
        }
        foreach (['attendance.default_radius_m', 'attendance.max_gps_accuracy_m', 'session.code_duration_minutes', 'exit.max_per_week', 'exit.min_hours_before', 'files.max_upload_mb', 'retention.selfie_days_after_batch'] as $int) {
            $flat[$int] = (int) $flat[$int];
        }
        foreach (['attendance.face_threshold', 'grade.threshold_a', 'grade.threshold_b', 'grade.threshold_c'] as $float) {
            $flat[$float] = (float) $flat[$float];
        }

        $settings->setMany(Arr::only($flat, array_keys(config('wokabulari.settings'))));

        Flux::toast(variant: 'success', text: 'Pengaturan tersimpan.');
    }
}; ?>

<div>
    <x-page-header title="Pengaturan sistem" subtitle="Nilai yang memengaruhi absen, sesi, asrama, penilaian, dan retensi data." />

    @unless (config('wokabulari.face_verification_enabled') && config('wokabulari.geofence_enabled'))
        <flux:callout icon="exclamation-triangle" color="red" class="mb-4"
                      heading="Verifikasi wajah atau geofence sedang nonaktif"
                      text="FACE_VERIFICATION_ENABLED/GEOFENCE_ENABLED bernilai false di .env. Wajib true di produksi." />
    @endunless

    <form wire:submit="save" class="space-y-4">
        <div class="grid gap-4 lg:grid-cols-2">
            <x-card title="Absen harian">
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="values.attendance.default_radius_m" type="number" label="Radius bawaan (m)" />
                    <flux:input wire:model="values.attendance.max_gps_accuracy_m" type="number" label="Akurasi GPS maks. (m)" />
                    <flux:input wire:model="values.attendance.face_threshold" type="number" step="0.01" label="Ambang kemiripan wajah" description="Jarak Euclidean; makin kecil makin ketat (bawaan 0,5)." />
                    <flux:input wire:model="values.attendance.open_from" type="time" label="Absen dibuka" />
                    <flux:input wire:model="values.attendance.late_after" type="time" label="Batas terlambat" />
                </div>
            </x-card>

            <x-card title="Sesi & kode kehadiran">
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="values.session.code_duration_minutes" type="number" label="Durasi kode (menit)" />
                    <flux:switch wire:model="values.session.validate_location" label="Validasi lokasi saat input kode" />
                </div>
            </x-card>

            <x-card title="Asrama">
                <div class="grid gap-3 sm:grid-cols-3">
                    <flux:input wire:model="values.dorm.check_window_start" type="time" label="Absen malam mulai" />
                    <flux:input wire:model="values.dorm.check_window_end" type="time" label="Absen malam selesai" />
                    <flux:input wire:model="values.dorm.curfew" type="time" label="Jam malam" />
                </div>
                <flux:switch class="mt-3" wire:model="values.dorm.notify_guardian_absent" label="Kabari wali bila anak belum absen asrama" />
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
