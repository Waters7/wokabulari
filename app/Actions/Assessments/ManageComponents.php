<?php

namespace App\Actions\Assessments;

use App\Enums\ComponentSource;
use App\Models\AssessmentComponentTemplate;
use App\Models\LearningSession;
use App\Models\SessionAssessmentComponent;
use App\Models\User;
use InvalidArgumentException;

/**
 * Komponen penilaian per sesi: tambah manual, pakai template, ubah, hapus, simpan sebagai template.
 */
class ManageComponents
{
    /**
     * @param  array{name: string, aspect: string, weight: float|int|string, max_score: float|int|string}  $data
     */
    public function add(LearningSession $session, array $data): SessionAssessmentComponent
    {
        return $session->components()->create([
            'name' => $data['name'],
            'aspect' => $data['aspect'],
            'weight' => (float) $data['weight'],
            'max_score' => (float) $data['max_score'],
            'source' => ComponentSource::Manual,
        ]);
    }

    /**
     * @param  list<int>  $templateIds
     */
    public function applyTemplates(LearningSession $session, User $teacher, array $templateIds): int
    {
        $templates = AssessmentComponentTemplate::query()->where('teacher_id', $teacher->id)->whereIn('id', $templateIds)->get();

        foreach ($templates as $template) {
            $this->add($session, $template->only(['name', 'aspect', 'weight', 'max_score']) + ['aspect' => $template->aspect->value]);
        }

        return $templates->count();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SessionAssessmentComponent $component, array $data): SessionAssessmentComponent
    {
        $this->ensureEditable($component);

        if ((float) $data['max_score'] < (float) ($component->scores()->max('score') ?? 0)) {
            throw new InvalidArgumentException('Skor maksimal lebih kecil dari skor yang sudah diinput.');
        }

        $component->update([
            'name' => $data['name'],
            'aspect' => $data['aspect'],
            'weight' => (float) $data['weight'],
            'max_score' => (float) $data['max_score'],
        ]);

        return $component;
    }

    public function delete(SessionAssessmentComponent $component): void
    {
        $this->ensureEditable($component);
        $component->delete();
    }

    public function saveAsTemplate(SessionAssessmentComponent $component, User $teacher): AssessmentComponentTemplate
    {
        return AssessmentComponentTemplate::query()->updateOrCreate(
            ['teacher_id' => $teacher->id, 'name' => $component->name],
            ['aspect' => $component->aspect, 'weight' => $component->weight, 'max_score' => $component->max_score],
        );
    }

    protected function ensureEditable(SessionAssessmentComponent $component): void
    {
        if (! $component->isEditable()) {
            throw new InvalidArgumentException('Komponen dari '.$component->source->label().' diisi otomatis dan tidak bisa diubah manual.');
        }
    }
}
