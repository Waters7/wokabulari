<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\Quiz;
use App\Services\Reports\QuizPaper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Unduh Excel soal (dengan kunci) dan hasil satu quiz. Hanya pengelola quiz.
 */
class QuizPaperExportController extends Controller
{
    public function __invoke(Request $request, Quiz $quiz, QuizPaper $paper): BinaryFileResponse
    {
        $this->authorize('manage', $quiz);
        $report = $paper->report($quiz);

        activity('laporan')->causedBy($request->user())->performedOn($quiz)->withProperties(['kelas' => $quiz->classroom->name])->log('Mengunduh soal & hasil quiz');

        return Excel::download(new ReportExport($report, [$quiz->classroom->label()]), Str::slug('soal-hasil '.$quiz->title).'-'.now()->format('Y-m-d').'.xlsx');
    }
}
