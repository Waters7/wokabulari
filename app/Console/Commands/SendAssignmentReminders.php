<?php

namespace App\Console\Commands;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Notifications\AssignmentReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('app:assignment-reminders {window=day : day (H-1, jam 18.00) atau 2h (2 jam sebelum tenggat)}')]
#[Description('Kirim pengingat tenggat tugas ke peserta yang belum mengumpulkan.')]
class SendAssignmentReminders extends Command
{
    public function handle(): int
    {
        $window = $this->argument('window') === '2h' ? '2h' : 'day';
        $column = $window === 'day' ? 'reminded_day_before_at' : 'reminded_two_hours_at';

        $query = Assignment::query()->where('status', AssignmentStatus::Published)->whereNull($column);

        $window === 'day'
            ? $query->whereBetween('due_at', [now()->addDay()->startOfDay(), now()->addDay()->endOfDay()])
            : $query->whereBetween('due_at', [now(), now()->addHours(2)]);

        $sent = 0;

        foreach ($query->with('classroom')->get() as $assignment) {
            $pending = $assignment->classroom->participants()->active()
                ->whereDoesntHave('assignmentSubmissions', fn ($q) => $q->where('assignment_id', $assignment->id)->where('status', '!=', 'revision_requested'))
                ->get();

            Notification::send($pending, new AssignmentReminderNotification($assignment, $window));
            $assignment->update([$column => now()]);
            $sent += $pending->count();
        }

        $this->info("{$sent} pengingat dikirim.");

        return self::SUCCESS;
    }
}
