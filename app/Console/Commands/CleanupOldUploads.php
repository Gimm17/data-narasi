<?php

namespace App\Console\Commands;

use App\Models\Report;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Artisan command untuk menghapus file upload yang sudah lama
 * Hanya menghapus file fisik, bukan record di database
 *
 * Usage:
 *   php artisan app:cleanup-uploads          # Hapus file > 30 hari
 *   php artisan app:cleanup-uploads --days=7 # Hapus file > 7 hari
 *   php artisan app:cleanup-uploads --dry-run # Preview tanpa menghapus
 */
class CleanupOldUploads extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:cleanup-uploads
                            {--days=30 : Jumlah hari sebelum file dianggap expired}
                            {--dry-run : Preview file yang akan dihapus tanpa benar-benar menghapus}';

    /**
     * The console command description.
     */
    protected $description = 'Hapus file upload yang sudah lama (default: > 30 hari) untuk menghemat storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $this->info("🧹 Cleanup uploads older than {$days} days (before {$cutoff->toDateString()})");

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE — tidak ada file yang akan dihapus');
        }

        // Ambil reports yang sudah selesai/gagal dan lebih tua dari cutoff
        $reports = Report::where('created_at', '<', $cutoff)
            ->whereIn('status', ['done', 'failed'])
            ->whereNotNull('original_path')
            ->get();

        if ($reports->isEmpty()) {
            $this->info('✅ Tidak ada file yang perlu dihapus.');
            return self::SUCCESS;
        }

        $deletedCount = 0;
        $freedBytes = 0;
        $errors = 0;

        $this->withProgressBar($reports, function (Report $report) use ($dryRun, &$deletedCount, &$freedBytes, &$errors) {
            $filesToDelete = array_filter([
                $report->original_path,
                $report->clean_path,
            ]);

            foreach ($filesToDelete as $filePath) {
                if (!Storage::exists($filePath)) {
                    continue;
                }

                try {
                    $fileSize = Storage::size($filePath);

                    if (!$dryRun) {
                        Storage::delete($filePath);
                    }

                    $deletedCount++;
                    $freedBytes += $fileSize;
                } catch (\Exception $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("  ❌ Gagal hapus {$filePath}: {$e->getMessage()}");
                }
            }

            // Hapus juga chart files jika ada
            if (!empty($report->chart_paths) && is_array($report->chart_paths)) {
                foreach ($report->chart_paths as $chartPath) {
                    if (Storage::exists($chartPath)) {
                        try {
                            $fileSize = Storage::size($chartPath);

                            if (!$dryRun) {
                                Storage::delete($chartPath);
                            }

                            $deletedCount++;
                            $freedBytes += $fileSize;
                        } catch (\Exception $e) {
                            $errors++;
                        }
                    }
                }
            }
        });

        $this->newLine(2);

        $freedMB = round($freedBytes / 1024 / 1024, 2);
        $action = $dryRun ? 'akan dihapus' : 'berhasil dihapus';

        $this->info("📊 Hasil:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Reports diproses', $reports->count()],
                ["File {$action}", $deletedCount],
                ['Storage dibebaskan', "{$freedMB} MB"],
                ['Error', $errors],
            ]
        );

        if ($dryRun) {
            $this->warn('💡 Jalankan tanpa --dry-run untuk benar-benar menghapus file.');
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
