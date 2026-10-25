<?php

namespace Database\Seeders\Demo;

use App\Enums\DormAttendanceStatus;
use App\Enums\DormRequestCategory;
use App\Enums\DormRequestStatus;
use App\Enums\ExitPermitStatus;
use App\Enums\ExitPermitType;
use App\Enums\HealthActionType;
use App\Enums\HealthReportStatus;
use App\Enums\HealthSeverity;
use App\Enums\ItemCondition;
use App\Enums\Priority;
use App\Models\DormAttendance;
use App\Models\DormExitPermit;
use App\Models\Dormitory;
use App\Models\DormRequest;
use App\Models\HealthReport;
use App\Models\User;
use App\Services\DormRoster;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data contoh Fase 8: absen asrama 2 minggu, 2 izin keluar, 3 keluhan, 3 laporan sakit dengan penanganan, inventaris kamar.
 */
class DormDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (DormAttendance::query()->exists()) {
            return;
        }

        $manager = User::query()->where('email', 'asrama@wokabulari.test')->first();
        $nurse = User::query()->where('email', 'kesehatan@wokabulari.test')->first();
        $roster = app(DormRoster::class);

        foreach (Dormitory::query()->with('rooms')->get() as $dormitory) {
            $residents = $roster->residents($dormitory);

            // Absen malam 14 hari terakhir dengan variasi status.
            foreach (range(14, 1) as $daysAgo) {
                $date = CarbonImmutable::today()->subDays($daysAgo);
                foreach ($residents->values() as $i => $user) {
                    $roll = ($i * 5 + $daysAgo * 3) % 20;
                    $status = match (true) {
                        $roll === 0 => DormAttendanceStatus::NotPresent,
                        $roll === 1 => DormAttendanceStatus::OnLeave,
                        $roll === 2 => DormAttendanceStatus::SickInRoom,
                        default => DormAttendanceStatus::Confirmed,
                    };
                    $self = $status === DormAttendanceStatus::Confirmed && $roll % 4 !== 0;
                    DormAttendance::query()->create([
                        'user_id' => $user->id,
                        'dormitory_id' => $dormitory->id,
                        'date' => $date,
                        'checked_in_at' => $self ? $date->setTime(20, 5 + ($i * 7) % 80) : null,
                        'status' => $status,
                        'attempts' => $self ? 1 : 0,
                        'confirmed_by' => $manager?->id,
                        'confirmed_at' => $date->setTime(21, 45),
                        'note' => $self ? null : ($status === DormAttendanceStatus::Confirmed ? 'Apel manual' : null),
                    ]);
                }
            }

            // Inventaris standar tiap kamar.
            foreach ($dormitory->rooms as $r => $room) {
                foreach ([['Tempat tidur susun', 3], ['Kasur', 6], ['Lemari pakaian', 3], ['Kipas angin', 1], ['Meja belajar', 2]] as $k => [$item, $qty]) {
                    $room->inventories()->create([
                        'item_name' => $item,
                        'quantity' => $qty,
                        'condition' => ($r + $k) % 7 === 0 ? ItemCondition::Damaged : ItemCondition::Good,
                        'note' => ($r + $k) % 7 === 0 ? 'Perlu diperbaiki' : null,
                    ]);
                }
            }
        }

        $participants = User::query()->participants()->whereHas('currentRoomAssignment')->with('currentRoomAssignment.room')->orderBy('id')->take(8)->get();
        if ($participants->count() < 6) {
            return;
        }

        // Izin keluar: satu sudah kembali, satu menunggu keputusan.
        DormExitPermit::query()->create([
            'user_id' => $participants[0]->id, 'type' => ExitPermitType::Temporary, 'reason' => 'Membeli perlengkapan mandi',
            'destination' => 'Mega Mall Manado', 'companion' => 'Teman sekamar', 'contact_phone' => '081234567801',
            'planned_departure' => now()->subDays(3)->setTime(15, 0), 'planned_return' => now()->subDays(3)->setTime(18, 0),
            'status' => ExitPermitStatus::Returned, 'approved_by' => $manager?->id, 'decided_at' => now()->subDays(4),
            'actual_departure' => now()->subDays(3)->setTime(15, 10), 'actual_return' => now()->subDays(3)->setTime(17, 40),
            'qr_token' => Str::random(40),
        ]);
        DormExitPermit::query()->create([
            'user_id' => $participants[1]->id, 'type' => ExitPermitType::HomeVisit, 'reason' => 'Ibadah keluarga dan ulang tahun nenek',
            'destination' => 'Tomohon', 'companion' => 'Kakak kandung', 'contact_phone' => '081234567802',
            'planned_departure' => now()->addDays(2)->setTime(9, 0), 'planned_return' => now()->addDays(3)->setTime(17, 0),
            'status' => ExitPermitStatus::Submitted,
        ]);

        // Keluhan asrama.
        foreach ([
            [2, DormRequestCategory::BrokenFacility, 'Keran kamar mandi bocor sejak kemarin.', DormRequestStatus::New, Priority::High, null],
            [3, DormRequestCategory::Supplies, 'Sabun cuci tangan di lantai 1 habis.', DormRequestStatus::InProgress, Priority::Normal, null],
            [4, DormRequestCategory::Cleanliness, 'Selokan depan asrama tersumbat.', DormRequestStatus::Resolved, Priority::Normal, 'Sudah dibersihkan petugas kebersihan.'],
        ] as [$i, $category, $description, $status, $priority, $note]) {
            DormRequest::query()->create([
                'user_id' => $participants[$i]->id, 'dorm_room_id' => $participants[$i]->currentRoomAssignment?->dorm_room_id,
                'category' => $category, 'description' => $description, 'priority' => $priority, 'status' => $status,
                'handled_by' => $status !== DormRequestStatus::New ? $manager?->id : null,
                'resolution_note' => $note, 'resolved_at' => $status === DormRequestStatus::Resolved ? now()->subDay() : null,
            ]);
        }

        // Laporan sakit dengan riwayat penanganan.
        foreach ([
            [5, ['fever', 'dizziness'], 38.3, HealthSeverity::Moderate, HealthReportStatus::Recovered, 4, [[HealthActionType::Examination, null, 'Demam, tenggorokan merah'], [HealthActionType::Medication, 'Paracetamol 500 mg', null], [HealthActionType::Resolved, null, 'Sudah tidak demam']]],
            [6, ['diarrhea', 'nausea'], 37.4, HealthSeverity::Mild, HealthReportStatus::Monitoring, 1, [[HealthActionType::Examination, null, 'Diare 3x sejak pagi'], [HealthActionType::Monitoring, null, 'Banyak minum oralit']]],
            [7, ['cough_cold'], null, HealthSeverity::Mild, HealthReportStatus::Reported, 0, []],
        ] as [$i, $symptoms, $temp, $severity, $status, $daysAgo, $actions]) {
            $user = $participants[$i] ?? $participants->last();
            $room = $user->currentRoomAssignment?->room;
            $reportedAt = $daysAgo ? now()->subDays($daysAgo)->setTime(19, 30) : now()->subHours(2);
            $report = HealthReport::query()->create([
                'user_id' => $user->id, 'reported_by' => $user->id, 'dormitory_id' => $room?->dormitory_id, 'dorm_room_id' => $room?->id,
                'symptoms' => $symptoms, 'description' => 'Contoh laporan sakit untuk uji coba.', 'started_at' => $reportedAt->subHours(4),
                'temperature' => $temp, 'severity' => $severity, 'status' => $status, 'reported_at' => $reportedAt,
                'resolved_at' => $status === HealthReportStatus::Recovered ? $reportedAt->addDays(2) : null,
            ]);
            foreach ($actions as $n => [$type, $medicine, $note]) {
                $report->actions()->create([
                    'handled_by' => $nurse?->id, 'action_type' => $type, 'medicine' => $medicine, 'dosage' => $medicine ? '3×1 sesudah makan' : null,
                    'condition_note' => $note, 'acted_at' => $reportedAt->addHours(($n + 1) * 2),
                    'next_check_at' => $type === HealthActionType::Monitoring ? now()->addHours(3) : null,
                ]);
            }
        }
    }
}
