<?php

namespace App\Actions\Participants;

class ImportResult
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    /** @var list<string> */
    public array $warnings = [];

    /** @var list<string> */
    public array $notes = [];

    public function warn(int $line, string $message): void
    {
        $this->warnings[] = "Baris {$line}: {$message}";
    }

    public function summary(): string
    {
        return "{$this->created} peserta baru, {$this->updated} diperbarui, {$this->skipped} dilewati.";
    }
}
