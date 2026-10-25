<?php

namespace App\Notifications;

use App\Models\Quiz;

class QuizPublishedNotification extends WokabulariNotification
{
    protected string $icon = 'pencil-square';

    public function __construct(public Quiz $quiz) {}

    public function title(): string
    {
        return $this->quiz->type->label().' baru: '.$this->quiz->title;
    }

    public function body(): string
    {
        $window = $this->quiz->opens_at ? 'Dibuka '.tanggal_jam($this->quiz->opens_at) : 'Sudah bisa dikerjakan';

        return $window.' · '.$this->quiz->duration_minutes.' menit.';
    }

    public function url(): ?string
    {
        return route('quizzes.index');
    }
}
