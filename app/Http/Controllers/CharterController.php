<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Services\Gamification\LeaderboardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Piagam apresiasi "Peserta Terbaik Minggu Ini" (PDF, A4 lanskap).
 */
class CharterController extends Controller
{
    public function __invoke(Request $request, LeaderboardService $leaderboard): Response
    {
        abort_unless($request->user()->can('gamification.manage') || $request->user()->can('dashboard.leader'), 403);

        $week = CarbonImmutable::parse($request->query('minggu', now()->toDateString()));
        $classroom = $request->filled('kelas') ? Classroom::query()->findOrFail($request->integer('kelas')) : null;
        $best = $leaderboard->bestOfWeek($week, $classroom);

        abort_unless($best, 404, 'Belum ada peserta dengan poin pada minggu ini.');

        $logo = fn (string $path) => file_exists($full = public_path($path)) ? 'data:image/png;base64,'.base64_encode(file_get_contents($full)) : null;

        $pdf = Pdf::loadView('pdf.charter', [
            'best' => $best,
            'classroom' => $classroom ?? $best['user']->activeClassroom(),
            'logos' => array_filter([
                $logo('images/logos/poltekkes-print.png') ?? $logo('images/logos/poltekkes.png'),
                $logo('images/logos/bp3mi.png'),
            ]),
            'brand' => $logo('images/brand/logo-horizontal.png'),
            'organizer' => setting('program.organizer'),
            'program' => setting('program.name'),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('piagam-'.Str::slug($best['user']->name).'-'.$best['from']->format('Y-m-d').'.pdf');
    }
}
