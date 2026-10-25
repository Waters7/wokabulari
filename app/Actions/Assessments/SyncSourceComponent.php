<?php

namespace App\Actions\Assessments;

use App\Enums\ComponentSource;
use App\Enums\LanguageAspect;
use App\Models\LearningSession;
use App\Models\SessionAssessmentComponent;
use App\Models\SessionScore;

/**
 * Komponen otomatis dari quiz/tugas yang ditautkan ke sesi. Skor ditulis dalam skala 0–100.
 */
class SyncSourceComponent
{
    public function ensure(LearningSession $session, ComponentSource $source, int $sourceId, string $name, LanguageAspect $aspect, float $weight = 20): SessionAssessmentComponent
    {
        $component = SessionAssessmentComponent::query()->firstOrNew(
            ['learning_session_id' => $session->id, 'source' => $source, 'source_id' => $sourceId],
        );

        // Bobot hanya diisi saat pertama dibuat; pengajar boleh menyesuaikannya kemudian.
        $component->weight ??= $weight;
        $component->fill(['name' => $name, 'aspect' => $aspect, 'max_score' => 100])->save();

        return $component;
    }

    public function setScore(SessionAssessmentComponent $component, int $userId, ?float $percentage): void
    {
        if ($percentage === null) {
            SessionScore::query()->where('session_assessment_component_id', $component->id)->where('user_id', $userId)->delete();

            return;
        }

        SessionScore::query()->updateOrCreate(
            ['session_assessment_component_id' => $component->id, 'user_id' => $userId],
            ['score' => round(max(0, min(100, $percentage)), 2)],
        );
    }

    public function remove(ComponentSource $source, int $sourceId): void
    {
        SessionAssessmentComponent::query()->where('source', $source)->where('source_id', $sourceId)->delete();
    }
}
