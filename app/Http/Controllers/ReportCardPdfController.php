<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\User;
use App\Services\Reports\ReportCard;
use App\Support\Letterhead;
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

        activity('laporan')->causedBy($request->user())->performedOn($user)->withProperties(['kelas' => $classroom->name])->log('Mengunduh rapor peserta');

        $pdf = Pdf::loadView('pdf.report-card', [
            'card' => $cards->for($classroom, $user),
            'kop' => Letterhead::pdf(),
            'printedAt' => now(),
            'printedBy' => $request->user()->name,
            'organizer' => setting('program.organizer'),
            'program' => setting('program.name'),
        ])->setPaper('a4');

        return response(Letterhead::output($pdf), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rapor-'.Str::slug($user->name).'-'.now()->format('Y-m-d').'.pdf"',
        ]);
    }
}
