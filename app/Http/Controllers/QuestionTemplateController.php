<?php

namespace App\Http\Controllers;

use App\Exports\QuestionTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Unduh template Excel import soal.
 */
class QuestionTemplateController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        return Excel::download(new QuestionTemplateExport, 'template-import-soal.xlsx');
    }
}
