<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\User;
use App\Services\Reports\ReportCard;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rapor peserta sebagai PDF (A4 tegak) untuk dicetak atau dibagikan ke peserta/wali.
 */
class ReportCardPdfController extends Controller
{
    public function __invoke(Request $request, Classroom $classroom, User $user, ReportCard $cards): Response
    {
        $this->authorize('viewScores', $classroom);
        abort_unless($classroom->participants()->whereKey($user->id)->exists(), 404);

        $logo = fn (string $path) => file_exists($full = public_path($path)) ? 'data:image/png;base64,'.base64_encode(file_get_contents($full)) : null;

        activity('laporan')->causedBy($request->user())->performedOn($user)->withProperties(['kelas' => $classroom->name])->log('Mengunduh rapor peserta');

        return Pdf::loadView('pdf.report-card', [
            'card' => $cards->for($classroom, $user),
            'brand' => $logo('images/brand/logo-horizontal.png'),
            'organizer' => setting('program.organizer'),
            'program' => setting('program.name'),
        ])->setPaper('a4')
            // Hanya huruf yang dipakai yang disematkan: ±90 KB, bukan ±900 KB.
            ->setOption('isFontSubsettingEnabled', true)
            ->download('rapor-'.Str::slug($user->name).'-'.now()->format('Y-m-d').'.pdf');
    }
}
