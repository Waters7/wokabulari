<?php

namespace App\Http\Requests;

use App\Enums\EvaluationQuestionType;
use App\Models\EvaluationQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class EvaluationResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('respondSurvey', $this->route('evaluation'));
    }

    public function rules(): array
    {
        $evaluation = $this->route('evaluation');

        return self::rulesFor($evaluation->questionsFor((string) $evaluation->roleFor($this->user())));
    }

    /**
     * Aturan jawaban per pertanyaan (kunci answers.{id}).
     *
     * @param  Collection<int, EvaluationQuestion>  $questions
     * @return array<string, mixed>
     */
    public static function rulesFor(Collection $questions): array
    {
        $rules = ['answers' => ['array']];

        foreach ($questions as $question) {
            $presence = $question->is_required ? 'required' : 'nullable';
            $rules["answers.{$question->id}"] = match ($question->type) {
                EvaluationQuestionType::Likert => [$presence, 'integer', 'between:1,5'],
                EvaluationQuestionType::Choice => [$presence, 'string', Rule::in($question->options ?? [])],
                EvaluationQuestionType::Text => [$presence, 'string', 'max:2000'],
            };
        }

        return $rules;
    }

    /**
     * @param  Collection<int, EvaluationQuestion>  $questions
     * @return array<string, string>
     */
    public static function attributesFor(Collection $questions): array
    {
        return $questions->mapWithKeys(fn (EvaluationQuestion $q) => ["answers.{$q->id}" => 'jawaban "'.str($q->body)->limit(40).'"'])->all();
    }
}
