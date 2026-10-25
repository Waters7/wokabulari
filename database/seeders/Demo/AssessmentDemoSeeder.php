<?php

namespace Database\Seeders\Demo;

use App\Actions\Assessments\SaveAssessment;
use App\Enums\LanguageAspect;
use App\Enums\PeriodType;
use App\Enums\SessionStatus;
use App\Models\AssessmentComponentTemplate;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\SessionNote;
use App\Models\SessionScore;
use App\Services\AssessmentSuggester;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Data contoh Fase 4: komponen & skor sesi, catatan, penilaian mingguan terbit.
 */
class AssessmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $batch = Batch::current();
        if (! $batch) {
            return;
        }

        $components = [
            ['name' => 'Speaking Practice', 'aspect' => LanguageAspect::Speaking, 'weight' => 40, 'max_score' => 100],
            ['name' => 'Kosakata Harian', 'aspect' => LanguageAspect::Vocabulary, 'weight' => 30, 'max_score' => 20],
            ['name' => 'Partisipasi', 'aspect' => LanguageAspect::Participation, 'weight' => 30, 'max_score' => 10],
        ];

        foreach (Classroom::query()->where('batch_id', $batch->id)->with(['participants', 'teacher'])->get() as $classroom) {
            foreach ($components as $template) {
                AssessmentComponentTemplate::query()->firstOrCreate(['teacher_id' => $classroom->teacher_id, 'name' => $template['name']], $template);
            }

            $sessions = $classroom->sessions()->where('status', SessionStatus::Completed)->get();

            foreach ($sessions as $sIndex => $session) {
                if ($session->components()->exists()) {
                    continue;
                }

                foreach ($components as $cIndex => $data) {
                    $component = $session->components()->create($data + ['source' => 'manual']);

                    foreach ($classroom->participants as $pIndex => $participant) {
                        // Variasi skor stabil per peserta dengan tren naik pelan.
                        $base = 62 + (($pIndex * 7) % 25) + min(10, $sIndex);
                        $pct = min(100, $base + (($cIndex + $sIndex) % 3) * 4);
                        SessionScore::query()->create([
                            'session_assessment_component_id' => $component->id,
                            'user_id' => $participant->id,
                            'score' => round($pct / 100 * $data['max_score'], 1),
                        ]);
                    }
                }

                if ($sIndex % 3 === 0 && $participant = $classroom->participants->get($sIndex % max(1, $classroom->participants->count()))) {
                    SessionNote::query()->create([
                        'learning_session_id' => $session->id,
                        'user_id' => $participant->id,
                        'teacher_id' => $classroom->teacher_id,
                        'aspect' => LanguageAspect::Speaking,
                        'note' => 'Pelafalan makin jelas. Latih lagi intonasi kalimat tanya, neh!',
                        'visible_to_guardian' => true,
                    ]);
                }
            }

            // Penilaian mingguan terbit untuk minggu-minggu yang sudah lewat.
            $save = app(SaveAssessment::class);
            $suggester = app(AssessmentSuggester::class);
            $weeks = $sessions->map(fn ($s) => $s->date->startOfWeek()->toDateString())->unique()
                ->filter(fn ($week) => CarbonImmutable::parse($week)->endOfWeek()->isPast());

            foreach ($weeks as $week) {
                [$start, $end] = PeriodType::Weekly->range($week);
                $assessments = [];

                foreach ($classroom->participants as $pIndex => $participant) {
                    $values = $suggester->suggest($classroom, $participant->id, $start, $end);
                    foreach (LanguageAspect::periodic() as $aspect) {
                        $values[$aspect->value] ??= 65 + (($pIndex * 5 + strlen($aspect->value) * 3) % 30);
                    }
                    $values['feedback'] = 'Terus semangat blajar! Fokus pada '.collect(LanguageAspect::periodic())->random()->label().' minggu depan.';
                    $assessments[] = $save->save($classroom, $participant, $classroom->teacher, PeriodType::Weekly, $start, $values);
                }

                $save->publish($assessments);
            }
        }
    }
}
