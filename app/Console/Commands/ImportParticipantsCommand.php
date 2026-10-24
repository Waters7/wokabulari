<?php

namespace App\Console\Commands;

use App\Actions\Participants\ImportParticipants;
use App\Imports\ParticipantRowsImport;
use App\Models\Classroom;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

#[Signature('app:import-participants
    {file : Path berkas Excel/CSV (kolom: nama, no_hp, asal_sekolah, ...)}
    {--kelas= : ID kelas tujuan (opsional)}
    {--password= : Kata sandi awal (bawaan: PARTICIPANT_DEFAULT_PASSWORD)}')]
#[Description('Import peserta dari Excel/CSV. Akun baru wajib mengganti kata sandi saat login pertama.')]
class ImportParticipantsCommand extends Command
{
    public function handle(ImportParticipants $importer): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("Berkas tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $classroom = $this->option('kelas') ? Classroom::find($this->option('kelas')) : null;

        if ($this->option('kelas') && ! $classroom) {
            $this->error('Kelas tidak ditemukan.');

            return self::FAILURE;
        }

        $reader = new ParticipantRowsImport;
        Excel::import($reader, $file);

        $result = $importer->handle($reader->rows, $classroom, $this->option('password'));

        $this->info($result->summary());

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        foreach ($result->notes as $note) {
            $this->line("  Catatan: {$note}");
        }

        return self::SUCCESS;
    }
}
