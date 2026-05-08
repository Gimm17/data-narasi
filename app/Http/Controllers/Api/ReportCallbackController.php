<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AIUsageLog;
use App\Models\AIProvider;
use App\Models\Report;
use App\Models\ReportOutput;
use App\Services\PythonServiceClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Controller untuk menerima callback dari Python Service
 * Dipanggil saat Python selesai memproses data
 */
class ReportCallbackController extends Controller
{
    protected PythonServiceClient $pythonClient;

    public function __construct(PythonServiceClient $pythonClient)
    {
        $this->pythonClient = $pythonClient;
    }

    /**
     * Handle callback dari Python service
     * Update report dengan hasil processing
     */
    public function __invoke(Request $request, Report $report)
    {
        try {
            // Validasi callback: HMAC signature (primary) + shared secret (fallback)
            $callbackSignature = $request->header('X-Callback-Signature');
            $callbackSecret = $request->header('X-Callback-Secret') ??
                             $request->input('callback_secret');
            $rawPayload = $request->getContent();

            if (!$this->pythonClient->validateCallback($callbackSignature, $callbackSecret, $rawPayload)) {
                Log::warning("Invalid callback authentication untuk Report ID {$report->id}", [
                    'has_signature' => !empty($callbackSignature),
                    'has_secret' => !empty($callbackSecret),
                ]);
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Validasi request data
            $validated = $request->validate([
                'status' => 'required|in:success,failed',
                'ai_narrative' => 'nullable|string',
                'summary_stats' => 'nullable|array',
                'cleaning_log' => 'nullable|array',
                'chart_paths' => 'nullable|array',
                'chart_images' => 'nullable|array',
                'chart_images.*.path' => 'nullable|string',
                'chart_images.*.data' => 'nullable|string',
                'chart_images.*.filename' => 'nullable|string',
                'clean_path' => 'nullable|string',
                'clean_rows' => 'nullable|integer',
                'ai_provider_used' => 'nullable|string',
                'processing_time_ms' => 'nullable|integer',
                'ai_usage_logs' => 'nullable|array',
                'outputs' => 'nullable|array',
                'error_message' => 'nullable|string',
                // Token & cost data
                'prompt_tokens' => 'nullable|integer',
                'completion_tokens' => 'nullable|integer',
                'total_tokens' => 'nullable|integer',
                'cost_usd' => 'nullable|numeric',
                'model_used' => 'nullable|string',
            ]);

            // Decode dan simpan chart images dari base64 (cross-container transfer)
            $chartPaths = $validated['chart_paths'] ?? [];
            if (!empty($validated['chart_images'])) {
                $chartPaths = []; // Rebuild from saved files
                foreach ($validated['chart_images'] as $chartImage) {
                    $chartPath = $chartImage['path'] ?? null;
                    $chartData = $chartImage['data'] ?? null;
                    $chartFilename = $chartImage['filename'] ?? null;

                    if ($chartData && $chartPath) {
                        try {
                            $decoded = base64_decode($chartData);
                            // Save to storage/app/public/charts/{report_id}/filename.png
                            Storage::disk('public')->put($chartPath, $decoded);
                            $chartPaths[] = $chartPath;
                            Log::info("Chart saved: {$chartPath}");
                        } catch (\Exception $e) {
                            Log::warning("Failed to save chart {$chartPath}: {$e->getMessage()}");
                            $chartPaths[] = $chartPath; // Keep path even if save fails
                        }
                    } elseif ($chartPath) {
                        $chartPaths[] = $chartPath;
                    }
                }
            }

            // Update report dengan hasil dari Python
            // total_rows diambil dari cleaning_log.original_rows (jumlah baris sebelum cleaning)
            $cleaningLog = $validated['cleaning_log'] ?? null;
            $totalRows = $cleaningLog['original_rows'] ?? $report->total_rows;

            $report->update([
                'ai_narrative' => $validated['ai_narrative'] ?? null,
                'summary_stats' => $validated['summary_stats'] ?? null,
                'cleaning_log' => $cleaningLog,
                'chart_paths' => $chartPaths,
                'clean_path' => $validated['clean_path'] ?? null,
                'total_rows' => $totalRows,
                'clean_rows' => $validated['clean_rows'] ?? 0,
                'ai_provider_used' => $validated['ai_provider_used'] ?? null,
                'processing_time_ms' => $validated['processing_time_ms'] ?? null,
                'prompt_tokens' => $validated['prompt_tokens'] ?? null,
                'completion_tokens' => $validated['completion_tokens'] ?? null,
                'total_tokens' => $validated['total_tokens'] ?? null,
                'cost_usd' => $validated['cost_usd'] ?? null,
                'model_used' => $validated['model_used'] ?? null,
            ]);

            // Simpan AI usage logs jika ada
            if (!empty($validated['ai_usage_logs'])) {
                foreach ($validated['ai_usage_logs'] as $logData) {
                    // Cari provider berdasarkan slug (Python kirim slug seperti 'gemini')
                    $providerName = $logData['provider_name'] ?? '';
                    $provider = AIProvider::where('slug', $providerName)->first()
                             ?? AIProvider::where('name', 'LIKE', "%{$providerName}%")->first();

                    // Skip jika provider tidak ditemukan di DB (belum di-seed)
                    if (!$provider) {
                        Log::warning("AI Provider '{$providerName}' tidak ditemukan di DB, skip usage log");
                        continue;
                    }

                    AIUsageLog::create([
                        'report_id'       => $report->id,
                        'ai_provider_id'  => $provider->id,
                        'prompt_length'   => $logData['prompt_tokens'] ?? 0,
                        'response_length' => $logData['completion_tokens'] ?? ($logData['tokens_used'] ?? 0),
                        'tokens_used'     => $logData['tokens_used'] ?? null,
                        'cost'            => $logData['cost_usd'] ?? null,
                        'response_time_ms' => $logData['response_time_ms'] ?? 0,
                        'is_success'      => ($logData['status'] ?? '') === 'success',
                        'error_message'   => $logData['error_message'] ?? null,
                    ]);

                    // Update provider counters
                    if ($provider) {
                        if (($logData['status'] ?? '') === 'success') {
                            $provider->incrementCalls();
                        } else {
                            $provider->incrementErrors();
                        }
                    }
                }
            }

            // Simpan report outputs jika ada
            if (!empty($validated['outputs'])) {
                foreach ($validated['outputs'] as $outputData) {
                    ReportOutput::create([
                        'report_id' => $report->id,
                        'output_type' => $outputData['type'],
                        'file_path' => $outputData['file_path'] ?? null,
                        'is_public' => $outputData['is_public'] ?? false,
                        'share_token' => $outputData['share_token'] ?? null,
                    ]);
                }
            }

            // Update status report berdasarkan hasil
            if ($validated['status'] === 'success') {
                $report->markAsDone();
            } else {
                $report->markAsFailed($validated['error_message'] ?? 'Processing gagal di Python service');
            }

            Log::info("Report ID {$report->id} berhasil diproses", [
                'status' => $validated['status'],
                'ai_provider_used' => $validated['ai_provider_used'],
                'processing_time_ms' => $validated['processing_time_ms'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Callback berhasil diproses',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error("Validation error untuk callback Report ID {$report->id}", [
                'errors' => $e->errors(),
            ]);

            $report->markAsFailed('Invalid callback data format');

            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error("Error processing callback untuk Report ID {$report->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $report->markAsFailed('Internal server error saat processing callback');

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
            ], 500);
        }
    }
}
