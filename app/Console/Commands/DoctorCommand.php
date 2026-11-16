<?php

namespace App\Console\Commands;

use App\Models\AIProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Memeriksa kesiapan server sebelum/sesudah deploy.
 *
 * Dirancang untuk shared hosting: hanya membaca, tidak mengubah apa pun.
 * Jalankan di server: php artisan datanarasi:doctor
 */
class DoctorCommand extends Command
{
    protected $signature = 'datanarasi:doctor';

    protected $description = 'Periksa kesiapan server (PHP, ekstensi, storage, database, AI provider)';

    /** @var array<int, array{0: string, 1: string, 2: bool}> */
    private array $checks = [];

    public function handle(): int
    {
        $this->info('Memeriksa kesiapan server...');
        $this->newLine();

        $this->checkPhp();
        $this->checkExtensions();
        $this->checkWritable();
        $this->checkStorage();
        $this->checkDatabase();
        $this->checkQueue();
        $this->checkProviders();

        $this->newLine();
        $failed = count(array_filter($this->checks, fn (array $c) => !$c[2]));

        $this->table(
            ['Status', 'Pemeriksaan', 'Detail'],
            array_map(fn (array $c) => [$c[2] ? 'OK' : 'GAGAL', $c[0], $c[1]], $this->checks)
        );

        if ($failed > 0) {
            $this->error("{$failed} pemeriksaan gagal — perbaiki dulu sebelum dipakai produksi.");

            return self::FAILURE;
        }

        $this->info('Semua pemeriksaan lolos.');

        return self::SUCCESS;
    }

    private function record(string $name, string $detail, bool $ok): void
    {
        $this->checks[] = [$name, $detail, $ok];
    }

    private function checkPhp(): void
    {
        $this->record(
            'Versi PHP',
            PHP_VERSION,
            version_compare(PHP_VERSION, '8.3.0', '>=')
        );

        // Zona waktu dipakai untuk tanggal laporan dan grafik dashboard.
        // config/app.php membacanya dari APP_TIMEZONE, jadi nilai yang muncul
        // di sini adalah nilai yang benar-benar berlaku.
        $this->record(
            'Zona waktu aplikasi',
            config('app.timezone') . ' (' . now()->format('H:i') . ')',
            in_array(config('app.timezone'), timezone_identifiers_list(), true)
        );
    }

    private function checkExtensions(): void
    {
        // GD wajib: tanpa FreeType chart PNG tidak bisa dibuat sama sekali.
        foreach (['gd', 'mbstring', 'fileinfo', 'zip', 'curl', 'openssl', 'pdo_mysql'] as $ext) {
            $this->record("Ekstensi {$ext}", extension_loaded($ext) ? 'aktif' : 'tidak aktif', extension_loaded($ext));
        }

        $freeType = extension_loaded('gd') && function_exists('imagettftext');
        $this->record('GD + FreeType', $freeType ? 'tersedia' : 'tidak tersedia (chart akan gagal)', $freeType);
    }

    private function checkWritable(): void
    {
        // Folder ini WAJIB ada, bukan hanya bisa ditulis. Laravel hanya membuat
        // sendiri storage/framework/cache dan storage/logs; folder sessions dan
        // views tidak — kalau hilang, setiap request jadi HTTP 500
        // "Please provide a valid cache path". Karena itu keberadaannya
        // diperiksa terpisah dari izin tulisnya.
        foreach ([
            'storage',
            'storage/app/private',
            'storage/app/public',
            'storage/framework',
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'bootstrap/cache',
        ] as $dir) {
            $path = base_path($dir);

            if (!is_dir($path)) {
                $this->record("Tulis {$dir}", 'TIDAK ADA — buat folder ini', false);

                continue;
            }

            $this->record("Tulis {$dir}", is_writable($path) ? 'bisa ditulis' : 'TIDAK bisa ditulis', is_writable($path));
        }
    }

    private function checkStorage(): void
    {
        // Font TTF wajib untuk chart; kalau hilang, imagettftext gagal.
        foreach (['DejaVuSans.ttf', 'DejaVuSans-Bold.ttf'] as $font) {
            $exists = is_file(resource_path('fonts/' . $font));
            $this->record("Font {$font}", $exists ? 'ada' : 'TIDAK ADA', $exists);
        }

        $assets = is_file(public_path('build/manifest.json'));
        $this->record('Aset frontend (public/build)', $assets ? 'ada' : 'TIDAK ADA — jalankan npm run build', $assets);

        // Tanpa symlink, chart tetap bisa dilayani lewat route storage.local
        // milik Laravel, tapi pastikan disk publik bisa ditulis.
        $writable = is_writable(storage_path('app/public'));
        $this->record('Disk publik bisa ditulis', $writable ? 'ya' : 'tidak', $writable);

        $hasLink = file_exists(public_path('storage'));
        $this->record(
            'Symlink public/storage',
            $hasLink ? 'ada' : 'tidak ada (pakai route storage/{path} bawaan Laravel)',
            true
        );
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->record('Koneksi database', DB::connection()->getDatabaseName(), true);
        } catch (\Throwable $e) {
            $this->record('Koneksi database', $e->getMessage(), false);

            return;
        }

        // Tabel inti harus ada; kalau belum, migration belum dijalankan.
        foreach (['reports', 'ai_providers', 'ai_usage_logs', 'jobs'] as $table) {
            $exists = \Illuminate\Support\Facades\Schema::hasTable($table);
            $this->record("Tabel {$table}", $exists ? 'ada' : 'TIDAK ADA — jalankan migrate', $exists);
        }

        // Kolom yang dipakai pipeline baru
        $columns = ['prompt_tokens', 'completion_tokens', 'total_tokens', 'cost_usd', 'model_used'];
        $missing = array_filter($columns, fn ($c) => !\Illuminate\Support\Facades\Schema::hasColumn('reports', $c));
        $this->record(
            'Kolom token & biaya di reports',
            $missing === [] ? 'lengkap' : 'kurang: ' . implode(', ', $missing),
            $missing === []
        );

        $hasEffort = \Illuminate\Support\Facades\Schema::hasColumn('ai_providers', 'reasoning_effort');
        $this->record('Kolom reasoning_effort', $hasEffort ? 'ada' : 'TIDAK ADA — jalankan migrate', $hasEffort);

        // Worker menyimpan antrean di tabel jobs untuk QUEUE_CONNECTION=database
        $driver = config('queue.default');
        $this->record('Queue driver', $driver, $driver === 'database' || $driver === 'redis');

        if ($driver === 'database') {
            $pending = DB::table('jobs')->count();
            $this->record('Job menunggu di antrean', (string) $pending, true);
        }
    }

    private function checkQueue(): void
    {
        $retryAfter = (int) config('queue.connections.database.retry_after', 0);
        $jobTimeout = 230;
        $this->record(
            'retry_after > timeout job',
            "retry_after={$retryAfter}, timeout job={$jobTimeout}",
            $retryAfter > $jobTimeout
        );

        // Konfigurasi cache yang ter-cache bisa membuat perubahan .env tidak berlaku
        $configCached = app()->configurationIsCached();
        $this->record(
            'Config ter-cache',
            $configCached ? 'ya (perubahan .env perlu config:cache ulang)' : 'tidak',
            true
        );
    }

    private function checkProviders(): void
    {
        try {
            $providers = AIProvider::orderBy('priority')->get();
        } catch (\Throwable $e) {
            return;
        }

        if ($providers->isEmpty()) {
            $this->record('AI provider', 'belum ada — jalankan db:seed', false);

            return;
        }

        $ready = $providers->filter(fn (AIProvider $p) => $p->isReady());
        $this->record(
            'AI provider siap pakai',
            $ready->count() . '/' . $providers->count() . ' (' . $ready->pluck('slug')->implode(', ') . ')',
            $ready->isNotEmpty()
        );
    }
}
