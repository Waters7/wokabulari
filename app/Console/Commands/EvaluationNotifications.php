<?php

namespace App\Console\Commands;

use App\Actions\Evaluations\PublishEvaluation;
use App\Models\ProgramEvaluation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:evaluation-notifications')]
#[Description('Beri tahu responden saat survei terjadwal dibuka dan ingatkan yang belum mengisi 24 jam sebelum tenggat.')]
class EvaluationNotifications extends Command
{
    public function handle(PublishEvaluation $publish): int
    {
        $opened = 0;
        $reminded = 0;

        foreach (ProgramEvaluation::query()->open()->whereNull('notified_at')->get() as $evaluation) {
            $opened += $publish->notify($evaluation);
        }

        // Pengingat 24 jam sebelum tenggat, tapi tidak dalam 6 jam sejak pemberitahuan awal (survei singkat).
        $closingSoon = ProgramEvaluation::query()->open()->whereNull('reminded_at')
            ->where('notified_at', '<=', now()->subHours(6))
            ->where('closes_at', '<=', now()->addDay())
            ->get();

        foreach ($closingSoon as $evaluation) {
            $reminded += $publish->remind($evaluation);
        }

        $this->info("{$opened} pemberitahuan survei baru, {$reminded} pengingat dikirim.");

        return self::SUCCESS;
    }
}
