<?php

namespace App\Http\Controllers;

use App\Models\MaterialAccessLog;
use App\Models\SessionMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sajikan berkas materi lewat signed URL. Akses peserta dicatat (dibuka/diunduh).
 */
class MaterialFileController extends Controller
{
    public function __invoke(Request $request, SessionMaterial $material): StreamedResponse
    {
        $download = $request->boolean('unduh');
        $this->authorize($download ? 'download' : 'view', $material);

        abort_unless($material->isFile() && Storage::disk('private')->exists($material->file_path), 404);

        if ($request->user()->isParticipant()) {
            MaterialAccessLog::record($material, $request->user(), $download);
        }

        $name = $material->original_name ?: basename($material->file_path);
        $headers = ['Content-Type' => $material->mime ?: 'application/octet-stream', 'Cache-Control' => 'private, max-age=1800'];

        return $download
            ? Storage::disk('private')->download($material->file_path, $name, $headers)
            : Storage::disk('private')->response($material->file_path, $name, $headers);
    }
}
