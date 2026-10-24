<?php

namespace App\Actions\Participants;

use App\Enums\Gender;
use App\Enums\Role;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Import peserta dari baris Excel/CSV (kolom heading sudah di-slug, mis. "No. HP" → no_hp).
 * Akun baru memakai kata sandi awal dan wajib mengganti kata sandi saat login pertama.
 */
class ImportParticipants
{
    /** Alias kolom yang diterima. */
    protected const COLUMNS = [
        'name' => ['nama', 'nama_lengkap', 'name'],
        'phone' => ['no_hp', 'nomor_hp', 'hp', 'no_whatsapp', 'phone', 'no_telepon'],
        'username' => ['nis', 'nisn', 'username', 'nis_nip'],
        'email' => ['email', 'e_mail'],
        'school_name' => ['asal_sekolah', 'sekolah', 'asal', 'asal_sekolah_kampus', 'lulusan'],
        'major' => ['jurusan', 'program_studi', 'prodi'],
        'gender' => ['jenis_kelamin', 'jk', 'gender', 'lp'],
        'birth_date' => ['tanggal_lahir', 'tgl_lahir'],
        'allergies' => ['alergi', 'alergi_pantangan', 'pantangan_makanan'],
        'medical_history' => ['riwayat_penyakit'],
        'emergency_contact_name' => ['kontak_darurat_nama', 'nama_kontak_darurat'],
        'emergency_contact_phone' => ['kontak_darurat_hp', 'hp_kontak_darurat'],
        'target_exam' => ['target_ujian'],
        'target_score' => ['target_skor'],
        'classroom' => ['kelas'],
        'note' => ['keterangan', 'catatan'],
    ];

    /**
     * @param  iterable<array<string, mixed>|Collection<string, mixed>>  $rows
     */
    public function handle(iterable $rows, ?Classroom $classroom = null, ?string $defaultPassword = null): ImportResult
    {
        $defaultPassword ??= config('wokabulari.participant_default_password');

        if (blank($defaultPassword)) {
            throw new InvalidArgumentException('Kata sandi awal belum diatur. Isi PARTICIPANT_DEFAULT_PASSWORD di .env atau berikan --password.');
        }

        $result = new ImportResult;
        $hashedPassword = Hash::make($defaultPassword);
        $line = 1;

        foreach ($rows as $row) {
            $line++;
            $data = $this->map($row instanceof Collection ? $row->all() : (array) $row);

            if (blank($data['name'])) {
                if (array_filter($data)) {
                    $result->warn($line, 'Nama kosong, baris dilewati.');
                    $result->skipped++;
                }

                continue;
            }

            try {
                DB::transaction(fn () => $this->importRow($data, $classroom, $hashedPassword, $result, $line));
            } catch (Throwable $e) {
                $result->skipped++;
                $result->warn($line, "{$data['name']}: gagal disimpan ({$e->getMessage()}).");
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function importRow(array $data, ?Classroom $classroom, string $hashedPassword, ImportResult $result, int $line): void
    {
        $user = $this->findExisting($data);
        $isNew = $user === null;

        if ($user?->trashed()) {
            $user->restore();
        }

        $user ??= new User([
            'password' => $hashedPassword,
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $user->fill(array_filter([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'username' => $data['username'],
            'email' => $data['email'],
        ], fn ($value) => filled($value)));

        $user->save();

        if (! $user->hasRole(Role::Participant->value)) {
            $user->assignRole(Role::Participant->value);
        }

        $user->profile()->updateOrCreate([], array_filter([
            'school_name' => $data['school_name'],
            'major' => $data['major'],
            'gender' => Gender::fromImport($data['gender'])?->value,
            'birth_date' => $this->parseDate($data['birth_date']),
            'allergies' => $data['allergies'],
            'medical_history' => $data['medical_history'],
            'emergency_contact_name' => $data['emergency_contact_name'],
            'emergency_contact_phone' => Phone::normalize($data['emergency_contact_phone']),
            'target_exam' => $data['target_exam'],
            'target_score' => $data['target_score'],
        ], fn ($value) => filled($value)));

        $target = $classroom ?? $this->findClassroom($data['classroom']);

        if ($target) {
            $target->participants()->syncWithoutDetaching([$user->id => ['joined_at' => now()]]);
        } elseif (filled($data['classroom'])) {
            $result->warn($line, "{$data['name']}: kelas \"{$data['classroom']}\" tidak ditemukan di angkatan aktif.");
        }

        if (blank($user->phone) && blank($user->username) && blank($user->email)) {
            $result->warn($line, "{$data['name']}: belum punya No. HP/NIS/email sehingga belum bisa login. Lengkapi di menu Peserta.");
        }

        if (filled($data['note'])) {
            $result->notes[] = "{$data['name']}: {$data['note']}";
        }

        $isNew ? $result->created++ : $result->updated++;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string|null>
     */
    protected function map(array $row): array
    {
        $row = collect($row)->mapWithKeys(fn ($value, $key) => [Str::slug((string) $key, '_') => $value]);

        $data = [];

        foreach (self::COLUMNS as $field => $aliases) {
            $value = collect($aliases)->map(fn ($alias) => $row->get($alias))->first(fn ($value) => filled($value));
            $data[$field] = is_string($value) ? trim($value) : ($value === null ? null : (string) $value);
        }

        // Excel kadang membuang angka 0 di depan nomor HP (085… → 85…)
        $data['phone'] = Phone::normalize($data['phone']);
        $data['email'] = $data['email'] ? Str::lower($data['email']) : null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function findExisting(array $data): ?User
    {
        $query = User::query()->withTrashed();

        foreach (['phone', 'username', 'email'] as $key) {
            if (filled($data[$key])) {
                $user = (clone $query)->where($key, $data[$key])->first();

                if ($user) {
                    return $user;
                }
            }
        }

        // Tanpa identitas login: cocokkan berdasarkan nama peserta yang sudah ada.
        if (blank($data['phone']) && blank($data['username']) && blank($data['email'])) {
            return User::query()->role(Role::Participant->value)->where('name', $data['name'])->first();
        }

        return null;
    }

    protected function findClassroom(?string $name): ?Classroom
    {
        if (blank($name)) {
            return null;
        }

        $batch = Batch::current();

        return Classroom::query()
            ->when($batch, fn ($query) => $query->where('batch_id', $batch->id))
            ->where('name', $name)
            ->first();
    }

    protected function parseDate(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                // Nomor seri tanggal Excel
                return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
            }

            foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y'] as $format) {
                $date = Carbon::createFromFormat($format, $value);

                if ($date !== false) {
                    return $date->toDateString();
                }
            }
        } catch (Throwable) {
            // Lanjut ke parse bebas
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
