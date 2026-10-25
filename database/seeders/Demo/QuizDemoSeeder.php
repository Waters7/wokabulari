<?php

namespace Database\Seeders\Demo;

use App\Actions\Quizzes\ManageQuiz;
use App\Actions\Quizzes\QuizAttemptService;
use App\Actions\Quizzes\SaveQuestion;
use App\Enums\LanguageAspect;
use App\Enums\QuestionType;
use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Enums\ReadingDisplay;
use App\Enums\ResultVisibility;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\QuestionBank;
use App\Models\Quiz;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

/**
 * Data contoh Fase 6: bank soal per bahasa, pretest (sudah dikerjakan), quiz aktif, quiz mendatang, tryout HSK 1.
 */
class QuizDemoSeeder extends Seeder
{
    public function run(): void
    {
        $batch = Batch::current();
        if (! $batch || Quiz::query()->exists()) {
            return;
        }

        Notification::fake(); // data contoh tidak perlu mengirim notifikasi

        $save = app(SaveQuestion::class);
        $manage = app(ManageQuiz::class);

        foreach (Classroom::query()->where('batch_id', $batch->id)->with(['language', 'teacher', 'participants'])->get() as $classroom) {
            $code = $classroom->language->code;
            $bank = QuestionBank::query()->create([
                'teacher_id' => $classroom->teacher_id,
                'language_id' => $classroom->language_id,
                'name' => $classroom->language->name.' — Kosakata Rumah Sakit',
                'level' => $classroom->level,
                'topic' => 'Rumah sakit',
            ]);

            $ids = collect($this->questions($code))->map(fn ($data) => $save->handle($bank, $data, allowMissingMedia: true)->id)->all();

            $common = [
                'learning_session_id' => null, 'aspect' => LanguageAspect::Vocabulary->value, 'duration_minutes' => 20,
                'max_attempts' => 1, 'passing_score' => 70, 'shuffle_questions' => true, 'shuffle_options' => true,
                'reading_display' => $code === 'zh' || $code === 'ja' ? ReadingDisplay::Ruby->value : ReadingDisplay::Hidden->value,
                'score_scale' => 100, 'instructions' => 'Kerjakan dengan jujur. Jawaban tersimpan otomatis.',
            ];

            // Pretest (sudah ditutup, semua peserta mengerjakan)
            $pretest = $manage->save($classroom, $classroom->teacher, array_merge($common, [
                'type' => QuizType::Pretest->value, 'title' => 'Pretest '.$classroom->language->name,
                'opens_at' => now()->subHour(), 'closes_at' => now()->addHour(), 'result_visibility' => ResultVisibility::AfterClose->value,
            ]));
            $manage->addQuestions($pretest, $ids);
            $pretest->update(['status' => QuizStatus::Published]);
            $this->simulateAttempts($pretest->fresh(), $classroom, 0.45);
            $pretest->update(['status' => QuizStatus::Closed, 'opens_at' => $batch->start_date->setTime(8, 0), 'closes_at' => $batch->start_date->setTime(10, 0)]);

            // Quiz aktif (sebagian peserta sudah mengerjakan)
            $active = $manage->save($classroom, $classroom->teacher, array_merge($common, [
                'type' => QuizType::Quiz->value, 'title' => 'Quiz Kosakata Minggu Ini',
                'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(3), 'result_visibility' => ResultVisibility::Immediately->value,
            ]));
            $manage->addQuestions($active, array_slice($ids, 0, 6));
            $active->update(['status' => QuizStatus::Published]);
            $this->simulateAttempts($active->fresh(), $classroom, 0.75, onlyFirst: 4);

            // Quiz mendatang
            $upcoming = $manage->save($classroom, $classroom->teacher, array_merge($common, [
                'type' => QuizType::Quiz->value, 'title' => 'Quiz Percakapan Pasien',
                'opens_at' => now()->addDays(4)->setTime(8, 0), 'closes_at' => now()->addDays(4)->setTime(20, 0), 'result_visibility' => ResultVisibility::Immediately->value,
            ]));
            $manage->addQuestions($upcoming, array_slice($ids, 2));
            $upcoming->update(['status' => QuizStatus::Published]);

            if ($code === 'zh') {
                $tryout = $manage->save($classroom, $classroom->teacher, array_merge($common, [
                    'type' => QuizType::Tryout->value, 'title' => 'Tryout HSK 1', 'duration_minutes' => 40, 'score_scale' => 200,
                    'reading_display' => ReadingDisplay::Hidden->value,
                    'opens_at' => now()->subHours(2), 'closes_at' => now()->addDays(5), 'result_visibility' => ResultVisibility::Immediately->value,
                ]));
                $manage->addQuestions($tryout, array_slice($ids, 0, 4), '听力 Menyimak (simulasi)');
                $manage->addQuestions($tryout, array_slice($ids, 4), '阅读 Membaca');
                $tryout->update(['status' => QuizStatus::Published]);
                $classroom->participants->each(fn ($p) => $p->profile?->update(['target_score' => '120']));
            }
        }
    }

    /** Peserta mengerjakan dengan peluang benar tertentu. */
    protected function simulateAttempts(Quiz $quiz, Classroom $classroom, float $accuracy, ?int $onlyFirst = null): void
    {
        $service = app(QuizAttemptService::class);
        $questions = $quiz->questions()->with('options')->get();

        foreach ($classroom->participants->take($onlyFirst ?? PHP_INT_MAX) as $index => $participant) {
            $attempt = $service->start($quiz, $participant, 'demo-'.$participant->id);
            $answers = [];

            foreach ($questions as $qIndex => $question) {
                $right = (($index * 3 + $qIndex * 7) % 100) / 100 < $accuracy + ($index % 3) * 0.05;
                $answers[$question->id] = match (true) {
                    $question->type->usesChoice() => ['option' => ($right ? $question->options->firstWhere('is_correct', true) : $question->options->firstWhere('is_correct', false))?->id],
                    $question->type === QuestionType::ShortAnswer => ['text' => $right ? $question->options->first()->body : 'tidak tahu'],
                    $question->type === QuestionType::Matching => ['pairs' => $question->options->mapWithKeys(fn ($o) => [$o->id => $right ? $o->match_key : '-'])->all()],
                    default => ['text' => 'Jawaban contoh'],
                };
            }

            $service->saveAnswers($attempt, $answers, 'demo-'.$participant->id);
            $service->submit($attempt->fresh());
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function questions(string $code): array
    {
        return match ($code) {
            'zh' => [
                ['type' => 'multiple_choice', 'body' => '医生', 'reading' => 'yīshēng', 'points' => 1, 'options' => [['body' => 'Dokter', 'is_correct' => true], ['body' => 'Perawat'], ['body' => 'Pasien'], ['body' => 'Apoteker']], 'explanation' => '医生 (yīshēng) = dokter'],
                ['type' => 'multiple_choice', 'body' => '你哪里不舒服？', 'reading' => 'Nǐ nǎlǐ bù shūfu?', 'points' => 1, 'options' => [['body' => 'Bagian mana yang sakit?', 'is_correct' => true], ['body' => 'Siapa namamu?'], ['body' => 'Kamu dari mana?'], ['body' => 'Jam berapa sekarang?']]],
                ['type' => 'tone', 'body' => '妈', 'reading' => 'ma', 'points' => 1, 'correct_tone' => 1, 'explanation' => '妈 mā (nada 1) = ibu'],
                ['type' => 'tone', 'body' => '马', 'reading' => 'ma', 'points' => 1, 'correct_tone' => 3, 'explanation' => '马 mǎ (nada 3) = kuda'],
                ['type' => 'true_false', 'body' => '护士 artinya perawat.', 'reading' => 'hùshi', 'points' => 1, 'correct_bool' => '1'],
                ['type' => 'short_answer', 'body' => 'Tulis hanzi untuk "terima kasih".', 'points' => 1, 'accepted_answers' => ['谢谢', 'xièxie', 'xiexie']],
                ['type' => 'matching', 'body' => 'Jodohkan kata dengan artinya.', 'points' => 3, 'options' => [['body' => '头', 'reading' => 'tóu', 'match_key' => 'kepala'], ['body' => '手', 'reading' => 'shǒu', 'match_key' => 'tangan'], ['body' => '药', 'reading' => 'yào', 'match_key' => 'obat']]],
                ['type' => 'essay', 'body' => '用中文介绍你自己。', 'reading' => 'Yòng Zhōngwén jièshào nǐ zìjǐ.', 'points' => 2],
            ],
            'ja' => [
                ['type' => 'multiple_choice', 'body' => '看護師', 'reading' => 'kangoshi', 'points' => 1, 'options' => [['body' => 'Perawat', 'is_correct' => true], ['body' => 'Dokter'], ['body' => 'Pasien'], ['body' => 'Keluarga']]],
                ['type' => 'multiple_choice', 'body' => 'どこが痛いですか。', 'reading' => 'Doko ga itai desu ka.', 'points' => 1, 'options' => [['body' => 'Bagian mana yang sakit?', 'is_correct' => true], ['body' => 'Mau makan apa?'], ['body' => 'Sudah minum obat?'], ['body' => 'Sedang apa?']]],
                ['type' => 'true_false', 'body' => '薬 artinya obat.', 'reading' => 'kusuri', 'points' => 1, 'correct_bool' => '1'],
                ['type' => 'short_answer', 'body' => 'Romaji dari ありがとう adalah …', 'points' => 1, 'accepted_answers' => ['arigatou', 'arigato', 'arigatō']],
                ['type' => 'matching', 'body' => 'Jodohkan.', 'points' => 3, 'options' => [['body' => '頭', 'reading' => 'atama', 'match_key' => 'kepala'], ['body' => '手', 'reading' => 'te', 'match_key' => 'tangan'], ['body' => '足', 'reading' => 'ashi', 'match_key' => 'kaki']]],
                ['type' => 'multiple_choice', 'body' => '車いす', 'reading' => 'kurumaisu', 'points' => 1, 'options' => [['body' => 'Kursi roda', 'is_correct' => true], ['body' => 'Tempat tidur'], ['body' => 'Tongkat'], ['body' => 'Mobil']]],
                ['type' => 'essay', 'body' => '自己紹介をしてください。', 'reading' => 'Jiko shōkai o shite kudasai.', 'points' => 2],
            ],
            'de' => [
                ['type' => 'multiple_choice', 'body' => 'die Krankenschwester', 'points' => 1, 'options' => [['body' => 'Perawat', 'is_correct' => true], ['body' => 'Dokter'], ['body' => 'Pasien'], ['body' => 'Apotek']]],
                ['type' => 'multiple_choice', 'body' => 'Wo haben Sie Schmerzen?', 'points' => 1, 'options' => [['body' => 'Di mana Anda merasa sakit?', 'is_correct' => true], ['body' => 'Siapa nama Anda?'], ['body' => 'Berapa umur Anda?'], ['body' => 'Anda tinggal di mana?']]],
                ['type' => 'true_false', 'body' => '"das Medikament" artinya obat.', 'points' => 1, 'correct_bool' => '1'],
                ['type' => 'short_answer', 'body' => 'Bahasa Jerman untuk "terima kasih":', 'points' => 1, 'accepted_answers' => ['danke', 'danke schön', 'danke schon']],
                ['type' => 'matching', 'body' => 'Jodohkan.', 'points' => 3, 'options' => [['body' => 'der Kopf', 'match_key' => 'kepala'], ['body' => 'die Hand', 'match_key' => 'tangan'], ['body' => 'das Bein', 'match_key' => 'kaki']]],
                ['type' => 'multiple_choice', 'body' => 'der Rollstuhl', 'points' => 1, 'options' => [['body' => 'Kursi roda', 'is_correct' => true], ['body' => 'Ranjang'], ['body' => 'Jendela'], ['body' => 'Pintu']]],
                ['type' => 'essay', 'body' => 'Stellen Sie sich bitte vor.', 'points' => 2],
            ],
            default => [
                ['type' => 'multiple_choice', 'body' => 'nurse', 'points' => 1, 'options' => [['body' => 'Perawat', 'is_correct' => true], ['body' => 'Dokter'], ['body' => 'Pasien'], ['body' => 'Apoteker']]],
                ['type' => 'multiple_choice', 'body' => 'Where does it hurt?', 'points' => 1, 'options' => [['body' => 'Bagian mana yang sakit?', 'is_correct' => true], ['body' => 'Kapan Anda datang?'], ['body' => 'Siapa keluarga Anda?'], ['body' => 'Mau minum apa?']]],
                ['type' => 'true_false', 'body' => '"Wheelchair" artinya kursi roda.', 'points' => 1, 'correct_bool' => '1'],
                ['type' => 'short_answer', 'body' => 'Bahasa Inggris dari "obat":', 'points' => 1, 'accepted_answers' => ['medicine', 'medication', 'drug']],
                ['type' => 'matching', 'body' => 'Match the words.', 'points' => 3, 'options' => [['body' => 'head', 'match_key' => 'kepala'], ['body' => 'hand', 'match_key' => 'tangan'], ['body' => 'leg', 'match_key' => 'kaki']]],
                ['type' => 'multiple_choice', 'body' => 'blood pressure', 'points' => 1, 'options' => [['body' => 'Tekanan darah', 'is_correct' => true], ['body' => 'Detak jantung'], ['body' => 'Suhu tubuh'], ['body' => 'Gula darah']]],
                ['type' => 'essay', 'body' => 'Introduce yourself as a nurse in 5 sentences.', 'points' => 2],
            ],
        };
    }
}
