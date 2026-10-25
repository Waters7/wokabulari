<?php

namespace App\Actions\Guardians;

use App\Enums\GuardianRelationship;
use App\Enums\Role;
use App\Models\GuardianInvitation;
use App\Models\User;
use App\Notifications\GuardianInvitationNotification;
use App\Notifications\GuardianLinkNotification;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Portal wali (SPEC 3.19): undangan, pendaftaran dengan kode, penautan anak, dan verifikasi admin.
 */
class ManageGuardians
{
    /** Huruf/angka tanpa karakter yang mudah tertukar (0/O, 1/I/L). */
    protected const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Admin mengundang wali (kode/tautan dikirim via WhatsApp/email bila diisi).
     */
    public function invite(User $participant, User $by, ?string $phone = null, ?string $email = null, ?string $relationship = null): GuardianInvitation
    {
        if (! $participant->isParticipant()) {
            throw new InvalidArgumentException('Undangan wali hanya untuk peserta.');
        }

        $invitation = GuardianInvitation::query()->create([
            'participant_id' => $participant->id,
            'phone' => Phone::normalize($phone),
            'email' => filled($email) ? mb_strtolower(trim($email)) : null,
            'relationship' => GuardianRelationship::tryFrom((string) $relationship),
            'code' => $this->newCode(),
            'expires_at' => now()->addDays(14),
            'created_by' => $by->id,
        ]);

        // Penerima belum punya akun: kirim on-demand ke email/WhatsApp yang diisi.
        if ($invitation->phone || $invitation->email) {
            Notification::route('mail', $invitation->email)->route('whatsapp', $invitation->phone)
                ->notify(new GuardianInvitationNotification($invitation->load('participant')));
        }

        return $invitation;
    }

    /**
     * Kode yang dibagikan peserta sendiri dari profilnya (dipakai ulang selama masih berlaku).
     */
    public function shareCode(User $participant): GuardianInvitation
    {
        if (! $participant->isParticipant()) {
            throw new InvalidArgumentException('Kode wali hanya untuk peserta.');
        }

        return GuardianInvitation::query()->where('participant_id', $participant->id)->where('created_by', $participant->id)->usable()->latest()->first()
            ?? GuardianInvitation::query()->create([
                'participant_id' => $participant->id,
                'code' => $this->newCode(),
                'expires_at' => now()->addDays(7),
                'created_by' => $participant->id,
            ]);
    }

    public function findUsable(string $code): GuardianInvitation
    {
        $invitation = GuardianInvitation::query()->with('participant')->where('code', strtoupper(trim($code)))->first();

        if (! $invitation || ! $invitation->isUsable()) {
            throw new InvalidArgumentException('Kode undangan tidak valid atau sudah kedaluwarsa. Minta kode baru ke anak atau panitia.');
        }

        return $invitation;
    }

    /**
     * Pendaftaran akun wali baru memakai kode undangan.
     *
     * @param  array{name: string, phone: string, email?: ?string, password: string, relationship: string}  $data
     */
    public function register(string $code, array $data): User
    {
        $invitation = $this->findUsable($code);
        $phone = Phone::normalize($data['phone']);

        if (User::query()->where('phone', $phone)->exists()) {
            throw new InvalidArgumentException('Nomor HP ini sudah terdaftar. Silakan masuk lalu tautkan anak dari menu Hubungkan Anak.');
        }

        return DB::transaction(function () use ($invitation, $data, $phone) {
            $guardian = User::query()->create([
                'name' => trim($data['name']),
                'phone' => $phone,
                'email' => filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null,
                'password' => Hash::make($data['password']),
                'is_active' => true,
                'must_change_password' => false,
            ]);
            $guardian->assignRole(Role::Guardian->value);

            $this->attach($invitation, $guardian, $data['relationship']);

            return $guardian;
        });
    }

    /**
     * Wali yang sudah punya akun menautkan anak lain dengan kode.
     */
    public function link(User $guardian, string $code, string $relationship): GuardianInvitation
    {
        if (! $guardian->isGuardian()) {
            throw new InvalidArgumentException('Hanya akun wali yang bisa menautkan anak.');
        }

        $invitation = $this->findUsable($code);

        if (DB::table('guardian_participant')->where('guardian_id', $guardian->id)->where('participant_id', $invitation->participant_id)->exists()) {
            throw new InvalidArgumentException('Anak ini sudah terhubung dengan akun Bapak/Ibu.');
        }

        DB::transaction(fn () => $this->attach($invitation, $guardian, $relationship));

        return $invitation;
    }

    public function verify(User $admin, User $guardian, User $participant): void
    {
        $updated = DB::table('guardian_participant')->where('guardian_id', $guardian->id)->where('participant_id', $participant->id)->whereNull('verified_at')
            ->update(['verified_at' => now(), 'verified_by' => $admin->id, 'updated_at' => now()]);

        if (! $updated) {
            throw new InvalidArgumentException('Hubungan ini tidak menunggu verifikasi.');
        }

        activity('wali')->causedBy($admin)->performedOn($guardian)->withProperties(['peserta' => $participant->name])->log('Memverifikasi hubungan wali');
        $guardian->notify(new GuardianLinkNotification($participant, 'verified'));
    }

    public function reject(User $admin, User $guardian, User $participant): void
    {
        DB::table('guardian_participant')->where('guardian_id', $guardian->id)->where('participant_id', $participant->id)->whereNull('verified_at')->delete();
        activity('wali')->causedBy($admin)->performedOn($guardian)->withProperties(['peserta' => $participant->name])->log('Menolak hubungan wali');
        $guardian->notify(new GuardianLinkNotification($participant, 'rejected'));
    }

    /**
     * Tautkan wali ke peserta. Undangan dari admin dengan nomor HP yang sama langsung terverifikasi;
     * selain itu menunggu verifikasi admin.
     */
    protected function attach(GuardianInvitation $invitation, User $guardian, string $relationship): void
    {
        $verified = $invitation->isFromAdmin() && $invitation->phone && $invitation->phone === $guardian->phone;

        DB::table('guardian_participant')->updateOrInsert(
            ['guardian_id' => $guardian->id, 'participant_id' => $invitation->participant_id],
            [
                'relationship' => GuardianRelationship::tryFrom($relationship)?->value ?? GuardianRelationship::Guardian->value,
                'is_primary' => ! DB::table('guardian_participant')->where('participant_id', $invitation->participant_id)->exists(),
                'verified_at' => $verified ? now() : null,
                'verified_by' => $verified ? $invitation->created_by : null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $invitation->update(['used_at' => now(), 'used_by' => $guardian->id]);

        if (! $verified) {
            Notification::send(User::query()->permission('guardian.verify')->active()->get(), new GuardianLinkNotification($invitation->participant, 'pending', $guardian));
        }
    }

    protected function newCode(): string
    {
        do {
            $code = collect(range(1, 8))->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])->implode('');
        } while (GuardianInvitation::query()->where('code', $code)->exists());

        return $code;
    }
}
