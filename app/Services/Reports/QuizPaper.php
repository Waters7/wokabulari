<?php

namespace App\Services\Reports;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\GradeService;
use Illuminate\Support\Collection;

/**
 * Soal (dengan kunci) dan hasil satu quiz untuk dicetak dan diunduh Excel. Hanya untuk pengelola quiz:
 * kunci jawaban tidak pernah dikirim ke peserta.
 */
class QuizPaper
{
    public function __construct(protected GradeService $grades) {}

    /**
     * Soal berurutan seperti di quiz.
     *
     * @return Collection<int, array{no: int, question: Question, points: float, options: list<array{label: string, option: QuestionOption}>, key: ?string}>
     */
    public function questions(Quiz $quiz): Collection
    {
        return $quiz->questions()->with('options')->get()->values()->map(fn (Question $question, int $i) => [
            'no' => $i + 1,
            'question' => $question,
            'points' => $quiz->pointsFor($question),
            'options' => $this->options($question),
            'key' => $this->key($question),
        ]);
    }

    /**
     * Hasil semua pengerjaan (urut nilai), peserta yang belum mengerjakan, dan ringkasan.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, missing: Collection<int, User>, average: ?float, highest: ?float, lowest: ?float, passed: int, graded: int}
     */
    public function results(Quiz $quiz): array
    {
        $attempts = $quiz->attempts()->with(['user', 'answers'])->get()
            ->sortBy([fn ($a, $b) => ($b->percentage ?? -1) <=> ($a->percentage ?? -1), fn ($a, $b) => strcmp($a->user->name, $b->user->name)])->values();

        // Peringkat hanya untuk yang sudah dinilai; nilai sama = peringkat sama.
        $rank = 0;
        $previous = null;
        $rows = $attempts->map(function (QuizAttempt $attempt, int $i) use (&$rank, &$previous) {
            if ($attempt->percentage !== null && $attempt->percentage !== $previous) {
                $rank = $i + 1;
                $previous = $attempt->percentage;
            }

            return ['attempt' => $attempt, 'rank' => $attempt->percentage !== null ? $rank : null];
        });

        $graded = $attempts->whereNotNull('percentage')->pluck('percentage');

        return [
            'rows' => $rows,
            'missing' => $quiz->classroom->participants()->active()->whereNotIn('users.id', $attempts->pluck('user_id'))->orderBy('name')->get(),
            'average' => GradeService::average($graded),
            'highest' => $graded->max(),
            'lowest' => $graded->min(),
            'passed' => $attempts->filter(fn (QuizAttempt $a) => $a->percentage !== null && $a->passed())->count(),
            'graded' => $graded->count(),
        ];
    }

    /**
     * Pilihan jawaban yang ditampilkan: huruf A, B, C untuk pilihan ganda & menyimak; teks apa adanya untuk yang lain.
     *
     * @return list<array{label: string, option: QuestionOption}>
     */
    public function options(Question $question): array
    {
        if (! $question->type->usesChoice()) {
            return [];
        }

        $lettered = in_array($question->type, [QuestionType::MultipleChoice, QuestionType::Listening], true);

        return $question->options->values()->map(fn (QuestionOption $option, int $i) => [
            'label' => $lettered ? chr(65 + $i) : ($question->type === QuestionType::Tone ? 'Nada '.$option->body : $option->body),
            'option' => $option,
        ])->all();
    }

    /** Kunci jawaban sebagai teks; esai/berbicara memakai pembahasan (bila ada). */
    public function key(Question $question): ?string
    {
        $type = $question->type;

        if ($type->usesChoice()) {
            $correct = collect($this->options($question))->first(fn ($o) => $o['option']->is_correct);

            return $correct ? $this->choiceText($question, $correct) : null;
        }

        return match ($type) {
            QuestionType::ShortAnswer => $question->options->pluck('body')->implode(' / ') ?: null,
            QuestionType::Matching => $question->options->map(fn (QuestionOption $o) => $o->body.' → '.$o->match_key)->implode('; ') ?: null,
            default => $question->explanation ? trim($question->explanation) : null,
        };
    }

    /** Jawaban peserta sebagai teks. */
    public function answer(Question $question, ?QuizAnswer $answer): ?string
    {
        if (! $answer) {
            return null;
        }

        $value = $answer->answer ?? [];

        if ($question->type === QuestionType::Speaking) {
            return $answer->audio_path ? 'Rekaman suara' : null;
        }

        if ($question->type->usesChoice()) {
            $chosen = collect($this->options($question))->first(fn ($o) => $o['option']->id === (int) ($value['option'] ?? 0));

            return $chosen ? $this->choiceText($question, $chosen) : null;
        }

        if ($question->type === QuestionType::Matching) {
            return collect($value['pairs'] ?? [])->filter(fn ($given) => filled($given))
                ->map(fn ($given, $id) => ($question->options->firstWhere('id', (int) $id)?->body ?? '?').' → '.$given)->implode('; ') ?: null;
        }

        return filled($value['text'] ?? null) ? trim((string) $value['text']) : null;
    }

    /** Status jawaban: Benar, Salah, Sebagian, Menunggu dinilai, atau Tidak dijawab. */
    public function verdict(?QuizAnswer $answer, float $points): string
    {
        return match (true) {
            ! $answer => 'Tidak dijawab',
            $answer->score === null => 'Menunggu dinilai',
            $answer->is_correct === true || ($points > 0 && $answer->score >= $points) => 'Benar',
            $answer->score > 0 => 'Sebagian',
            default => 'Salah',
        };
    }

    /**
     * Isi Excel: lembar Soal, Hasil, Nilai per soal, dan Jawaban (format sama dengan Pusat Laporan).
     *
     * @return array<string, mixed>
     */
    public function report(Quiz $quiz): array
    {
        $quiz->loadMissing(['classroom.batch']);
        $questions = $this->questions($quiz);
        $results = $this->results($quiz);
        $finished = $results['rows']->filter(fn ($row) => $row['attempt']->status !== AttemptStatus::InProgress)->values();
        $answersOf = fn (QuizAttempt $attempt) => $attempt->answers->keyBy('question_id');
        // Peserta dengan beberapa percobaan: "Nama (percobaan 2)".
        $multi = $results['rows']->countBy(fn ($row) => $row['attempt']->user_id);
        $order = $results['rows']->pluck('attempt')->sortBy('started_at')->groupBy('user_id')
            ->flatMap(fn (Collection $list) => $list->values()->mapWithKeys(fn (QuizAttempt $a, int $i) => [$a->id => $i + 1]));
        $name = fn (QuizAttempt $a) => $a->user->name.($multi[$a->user_id] > 1 ? ' (percobaan '.$order[$a->id].')' : '');

        $answers = [];
        foreach ($finished as $row) {
            $byQuestion = $answersOf($row['attempt']);
            foreach ($questions as $item) {
                $answer = $byQuestion->get($item['question']->id);
                $answers[] = [
                    $name($row['attempt']), $item['no'], $item['question']->type->label(), $this->answer($item['question'], $answer),
                    $item['key'], $this->verdict($answer, $item['points']), $answer?->score, $item['points'], $answer?->teacher_feedback,
                ];
            }
        }

        return [
            'title' => 'Soal & hasil '.$quiz->title,
            'period' => $quiz->classroom->label().' · '.$quiz->type->label().' · '.$questions->count().' soal · '.poin($questions->sum('points')).' poin',
            'summary' => [],
            'sheets' => [
                [
                    'title' => 'Soal',
                    'headings' => ['No', 'Jenis', 'Soal', 'Cara baca', 'Pilihan jawaban', 'Kunci jawaban', 'Poin', 'Bagian'],
                    'rows' => $questions->map(fn (array $item) => [
                        $item['no'], $item['question']->type->label(), $item['question']->body, $item['question']->reading,
                        collect($item['options'])->map(fn ($o) => $this->choiceText($item['question'], $o))->implode("\n") ?: null,
                        $item['key'], $item['points'], $item['question']->pivot->section,
                    ])->all(),
                ],
                [
                    'title' => 'Hasil',
                    'headings' => ['Peringkat', 'Nama', 'NIS', 'Mulai', 'Dikumpulkan', 'Status', 'Skor', 'Poin maksimal', 'Nilai', 'Predikat', 'Keterangan', 'Pindah tab'],
                    'rows' => [
                        ...$results['rows']->map(fn (array $row) => [
                            $row['rank'], $name($row['attempt']), $row['attempt']->user->username, tanggal_jam($row['attempt']->started_at),
                            $row['attempt']->submitted_at ? tanggal_jam($row['attempt']->submitted_at) : null, $row['attempt']->status->label(),
                            $row['attempt']->score, $row['attempt']->max_score, $row['attempt']->percentage, $this->grades->grade($row['attempt']->percentage),
                            $row['attempt']->percentage === null ? null : ($row['attempt']->passed() ? 'Lulus' : 'Belum lulus'), $row['attempt']->tab_switch_count ?: null,
                        ])->all(),
                        ...$results['missing']->map(fn (User $user) => [null, $user->name, $user->username, null, null, 'Belum mengerjakan', null, null, null, null, null, null])->all(),
                    ],
                ],
                [
                    'title' => 'Nilai per soal',
                    'headings' => ['Nama', 'NIS', ...$questions->map(fn ($item) => 'No '.$item['no'].' (maks '.poin($item['points']).')')->all(), 'Total'],
                    'rows' => $finished->map(function (array $row) use ($questions, $answersOf, $name) {
                        $byQuestion = $answersOf($row['attempt']);

                        return [$name($row['attempt']), $row['attempt']->user->username,
                            ...$questions->map(fn ($item) => $byQuestion->get($item['question']->id)?->score)->all(), $row['attempt']->score];
                    })->all(),
                ],
                [
                    'title' => 'Jawaban',
                    'headings' => ['Nama', 'No', 'Jenis', 'Jawaban peserta', 'Kunci jawaban', 'Hasil', 'Poin', 'Maks', 'Umpan balik'],
                    'rows' => $answers,
                ],
            ],
        ];
    }

    /** "B. Halo", "Benar", "Nada 3". */
    protected function choiceText(Question $question, array $choice): string
    {
        return in_array($question->type, [QuestionType::MultipleChoice, QuestionType::Listening], true)
            ? $choice['label'].'. '.$choice['option']->body
            : $choice['label'];
    }
}
