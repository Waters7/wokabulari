<?php

namespace App\Notifications;

use App\Models\Quiz;

/**
 * Pengajar me-reset pengerjaan quiz: peserta boleh mengerjakan ulang.
 */
class QuizRetakeNotification extends WokabulariNotification
{
    protected string $icon = 'arrow-path';

    public function __construct(public Quiz $quiz) {}

    public function title(): string
    {
        return 'Silakan kerjakan ulang: '.$this->quiz->title;
    }

    public function body(): string
    {
        return 'Pengajar membuka kembali '.strtolower($this->quiz->type->label()).' ini untukmu'
            .($this->quiz->closes_at ? ' sampai '.tanggal_jam($this->quiz->closes_at) : '').'.';
    }

    public function url(): ?string
    {
        return route('quizzes.index');
    }
}
