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
 * Dapat diakses tanpa login — tracking via visitor_token cookie
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
                'icon' => $t->icon(),
                'description' => $t->description(),
            ], \App\Enums\AnalysisType::cases()),
            'tones' => array_map(fn($t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'icon' => $t->icon(),
                'description' => $t->description(),
            ], \App\Enums\NarrativeTone::cases()),
        ]);
    }

    /**
     * Handle file upload dan buat record Report baru
     * Mendukung anonymous upload via visitor_token
     */
    public function store(UploadFileRequest $request)
    {
        $user = auth()->user();
        $file = $request->file('file');

        // Generate/retrieve visitor token untuk tracking anonymous uploads
        $visitorToken = $request->cookie('dn_visitor')
            ?? Str::uuid()->toString();

        // Generate unique filename
        $originalFilename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $uniqueFilename = Str::uuid() . '.' . $extension;

        // Simpan file ke storage — gunakan visitor_token sebagai folder jika anonymous
        $folder = $user ? "uploads/{$user->id}" : "uploads/guest_{$visitorToken}";
        $filePath = $file->storeAs($folder, $uniqueFilename, 'local');

        // Buat record Report baru
        $report = Report::create([
            'user_id' => $user?->id,
            'visitor_token' => $visitorToken,
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

        // Set visitor token cookie (30 hari) dan redirect
        return redirect()->route('reports.processing', $report->id)
            ->with('success', 'File berhasil diupload. Sedang diproses...')
            ->cookie('dn_visitor', $visitorToken, 60 * 24 * 30); // 30 hari
    }
}
