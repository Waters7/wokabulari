<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('setting.manage');
    }

    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * Kunci pengaturan memakai titik (attendance.late_after), sehingga aturan ditulis per kunci.
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        $time = ['required', 'date_format:H:i'];

        return [
            'attendance.default_radius_m' => ['required', 'integer', 'between:10,5000'],
            'attendance.face_threshold' => ['required', 'numeric', 'between:0.2,0.9'],
            'attendance.max_gps_accuracy_m' => ['required', 'integer', 'between:5,1000'],
            'attendance.open_from' => $time,
            'attendance.late_after' => $time,
            'session.code_duration_minutes' => ['required', 'integer', 'between:1,180'],
            'session.validate_location' => ['boolean'],
            'session.documentation_required' => ['boolean'],
            'session.documentation_from' => ['required', 'date_format:Y-m-d'],
            'dorm.check_window_start' => $time,
            'dorm.check_window_end' => $time,
            'dorm.curfew' => $time,
            'dorm.late_until' => $time,
            'dorm.notify_guardian_absent' => ['boolean'],
            'dorm.auto_confirm' => ['boolean'],
            'exit.max_per_week' => ['required', 'integer', 'between:0,14'],
            'exit.min_hours_before' => ['required', 'integer', 'between:0,168'],
            'exit.guardian_required_types' => ['array'],
            'exit.guardian_required_types.*' => [Rule::in(['temporary', 'home_visit', 'overnight'])],
            'files.max_upload_mb' => ['required', 'integer', 'between:1,200'],
            'grade.threshold_a' => ['required', 'numeric', 'between:0,100'],
            'grade.threshold_b' => ['required', 'numeric', 'between:0,100', 'lt:grade.threshold_a'],
            'grade.threshold_c' => ['required', 'numeric', 'between:0,100', 'lt:grade.threshold_b'],
            'retention.selfie_days_after_batch' => ['required', 'integer', 'between:7,3650'],
            'leader.weekly_email' => ['boolean'],
            'program.name' => ['required', 'string', 'max:150'],
            'program.organizer' => ['required', 'string', 'max:150'],
            'program.dorm_contact' => ['nullable', 'string', 'max:30'],
        ];
    }
}
