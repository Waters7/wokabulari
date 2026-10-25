<?php

namespace App\Console\Commands;

use App\Actions\Quizzes\QuizAttemptService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:submit-expired-attempts')]
#[Description('Kumpulkan otomatis attempt quiz yang waktunya habis (termasuk yang ditinggalkan).')]
class SubmitExpiredQuizAttempts extends Command
{
    public function handle(QuizAttemptService $attempts): int
    {
        $this->info($attempts->submitExpired().' attempt dikumpulkan otomatis.');

        return self::SUCCESS;
    }
}
