<?php

namespace App\Http\Controllers;

use App\Exports\ParticipantTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Unduh template Excel import peserta.
 */
class ParticipantTemplateController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        return Excel::download(new ParticipantTemplateExport, 'template-import-peserta.xlsx');
    }
}
