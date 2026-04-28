<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadFileRequest;
use App\Jobs\ProcessDataJob;
use App\Models\Report;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Controller untuk handle upload file CSV/Excel
 */
class UploadController extends Controller
{
    /**
     * Tampilkan halaman upload
     */
    public function create()
    {
        return Inertia::render('Upload', [
            'analysisTypes' => array_map(fn($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ], \App\Enums\AnalysisType::cases()),
            'tones' => array_map(fn($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ], \App\Enums\NarrativeTone::cases()),
        ]);
    }

    /**
     * Handle file upload dan buat record Report baru
     */
    public function store(UploadFileRequest $request)
    {
        $user = auth()->user();
        $file = $request->file('file');

        // Generate unique filename
        $originalFilename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $uniqueFilename = Str::uuid() . '.' . $extension;

        // Simpan file ke storage/app/uploads/{user_id}/
        $filePath = $file->storeAs(
            "uploads/{$user->id}",
            $uniqueFilename,
            'local'
        );

        // Buat record Report baru
        $report = Report::create([
            'user_id' => $user->id,
            'title' => $request->getTitle(),
            'original_filename' => $originalFilename,
            'original_path' => $filePath,
            'status' => \App\Enums\ReportStatus::Pending,
            'dataset_type' => $request->getAnalysisType()->value,
            'tone' => $request->getTone()->value,
            'total_rows' => 0,
            'clean_rows' => 0,
        ]);

        // Dispatch ProcessDataJob untuk diproses di background
        ProcessDataJob::dispatch($report->id);

        // Redirect ke halaman processing
        return redirect()->route('reports.processing', $report->id)
            ->with('success', 'File berhasil diupload. Sedang diproses...');
    }
}
