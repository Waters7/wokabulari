<?php

namespace App\Console\Commands;

use App\Actions\Sessions\EndSession;
use App\Enums\SessionPhase;
use App\Enums\SessionStatus;
use App\Models\LearningSession;
use App\Models\User;
use App\Services\SessionDocumentationRule;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Selesaikan sesi yang macet "berlangsung" (mis. foto akhir sesi gagal terunggah karena sinyal),
 * pada jam tertentu dan tanpa menunggu dokumentasi akhir. Pengajar tetap bisa menambah foto akhir sesudahnya.
 */
#[Signature('app:end-session {session : ID sesi} {--at= : Jam selesai (Y-m-d H:i, WITA); bawaan = jam selesai terjadwal} {--by= : Email/ID pengguna pencatat; bawaan = pengajar sesi} {--dry-run : Tampilkan saja, tanpa mengubah data}')]
#[Description('Selesaikan sesi yang macet berlangsung pada jam tertentu tanpa menunggu foto akhir sesi.')]
class EndStuckSession extends Command
{
    public function handle(EndSession $end, SessionDocumentationRule $documentation): int
    {
        $session = LearningSession::query()->with('classroom')->find($this->argument('session'));

        if (! $session) {
            $this->error('Sesi tidak ditemukan.');

            return self::FAILURE;
        }

        if ($session->status !== SessionStatus::Ongoing) {
            $this->error("Sesi #{$session->id} berstatus {$session->status->label()}, bukan Berlangsung. Tidak diubah.");

            return self::FAILURE;
        }

        $endedAt = $this->option('at')
            ? CarbonImmutable::parse($this->option('at'), config('app.timezone'))
            : CarbonImmutable::parse($session->date->toDateString().' '.$session->end_time, config('app.timezone'));

        if ($endedAt->isFuture() || ($session->started_at && $endedAt->lt($session->started_at))) {
            $this->error('Jam selesai harus sesudah sesi dimulai ('.($session->started_at?->format('d-m-Y H:i') ?? '–').') dan tidak di masa depan.');

            return self::FAILURE;
        }

        $by = $this->option('by')
            ? User::query()->where('email', $this->option('by'))->orWhere('id', $this->option('by'))->first()
            : $session->teacher;

        if (! $by) {
            $this->error('Pengguna pencatat tidak ditemukan.');

            return self::FAILURE;
        }

        $missingDoc = $documentation->missing($session, SessionPhase::End);
        $this->line("Sesi #{$session->id} · {$session->topic} · {$session->classroom->name} · ".tanggal($session->date));
        $this->line('Selesai: '.$endedAt->format('d-m-Y H:i').' WITA · dicatat oleh '.$by->name.($missingDoc ? ' · foto akhir sesi belum ada' : ''));

        if ($this->option('dry-run')) {
            $this->info('Uji coba saja, data tidak diubah.');

            return self::SUCCESS;
        }

        $marked = $end->handle($session, $by, $endedAt, requireDocumentation: false);

        activity('sesi')->performedOn($session)->causedBy($by)
            ->withProperties(['selesai' => $endedAt->toDateTimeString(), 'tanpa_foto_akhir' => $missingDoc, 'lewat' => 'app:end-session'])
            ->log('Sesi macet diselesaikan admin');

        $this->info("Sesi diselesaikan. {$marked} peserta yang belum input kode ditandai otomatis (alpa, atau izin/sakit bila ada izin disetujui).");

        return self::SUCCESS;
    }
}
