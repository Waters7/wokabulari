<?php

namespace App\Http\Controllers;

use App\Exports\VocabularyTemplateExport;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Unduh templat Excel import Bank Kosakata (contoh baris sesuai bahasa ?bahasa=).
 */
class VocabularyTemplateController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $language = Language::query()->find($request->integer('bahasa'));

        return Excel::download(new VocabularyTemplateExport($language), 'template-bank-kosakata'.($language ? '-'.Str::slug($language->name) : '').'.xlsx');
    }
}
