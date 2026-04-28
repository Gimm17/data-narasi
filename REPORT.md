# Laporan Komprehensif Pemulihan & Perbaikan DataNarasi (Sesi 14)

Laporan ini merinci seluruh rentetan isu yang terjadi, proses investigasi, *root cause analysis*, hingga implementasi perbaikan pada *repository* **DataNarasi** untuk memulihkan fungsi aplikasi secara total.

---

## 1. Timeline & Kronologi Kerusakan
- **Awal Masalah**: Eksekusi perintah instalasi sistem autentikasi **Laravel Breeze** (`php artisan breeze:install`) menimpa ( *overwrite* ) beberapa file vital bawaan proyek, terutama `routes/web.php` dan kemungkinan berdampak pada integrasi *layout frontend*.
- **Gejala Utama**: 
  - Seluruh *custom route* (mis. `/upload`, `/reports`, `/admin`) mengembalikan error **HTTP 404 (Not Found)**.
  - Setelah *routing* dikembalikan, layar aplikasi memunculkan serangkaian **HTTP 500 (Internal Server Error)** terkait Database SQL Mismatch dan Vite SSR Connection Logging.

---

## 2. Detail Isolasi & Perbaikan Bug (*Changelog*)

### Isu A: Kehilangan Rute Kustom (*Routing Wipeout*)
- **File Terdampak**: `routes/web.php`
- **Root Cause**: Laravel Breeze secara *default* akan mem- *publish* ulang `web.php` yang hanya berisi rute `/` (welcome), `/dashboard`, dan `/profile`. Rute-rute aplikasi utama terhapus.
- **Tindakan Perbaikan**:
  - Merekonstruksi blok rute `Route::middleware('auth')`.
  - Mengembalikan rute `Route::get('/upload')` (UploadController)
  - Mengembalikan rute `Route::get('/reports')` (ReportController)
  - Mengembalikan grup rute `prefix('admin')` (AIProviderController)

### Isu B: Inkonsistensi Nama Tabel Database (ORM Mismatch)
- **File Terdampak**: `app/Models/AIUsageLog.php`
- **Error yang Muncul**: `Base table or view not found: 1146 Table 'data_narasi.a_i_usage_logs' doesn't exist`
- **Root Cause**: Laravel Eloquent mem-*pluralize* (menjamakkan) nama class `AIUsageLog` menjadi `a_i_usage_logs`. Namun, di skema *database MySQL*, tabel tersebut bernama `ai_usage_logs`.
- **Tindakan Perbaikan**: 
  - Menambahkan *property override*: `protected $table = 'ai_usage_logs';` pada model `AIUsageLog`.

### Isu C: Foreign Key & Column Name Mismatch 
- **File Terdampak**: 
  1. `app/Models/AIUsageLog.php`
  2. `app/Models/AIProvider.php`
  3. `app/Http/Controllers/Admin/AIProviderController.php`
- **Error yang Muncul**: `Column not found: 1054 Unknown column 'ai_usage_logs.provider_id'` pada halaman `http://localhost:8000/admin/ai-providers`.
- **Root Cause**: Kode lama mencoba mengakses kolom `provider_id` dan kolom rekaman log menggunakan `status = 'success'`. Di skema *database* terbaru, kolom relasinya bernama `ai_provider_id` dan tipe rekaman keberhasilan dipindah ke kolom *boolean* `is_success`.
- **Tindakan Perbaikan**:
  - Di `AIProviderController`, mengubah `sum(case when status = "success" then 1 else 0 end)` menjadi `sum(is_success)`.
  - Di `AIUsageLog.php` (Model), mengubah *fillable array* dari `provider_id` ke `ai_provider_id` dan di metrik relasi `provider()` menggunakan `.ai_provider_id`.
  - Di `AIProvider.php` (Model), memperbaiki method `aiUsageLogs()` dari `return $this->hasMany(AIUsageLog::class, 'provider_id')` menjadi `return $this->hasMany(... 'ai_provider_id')`.

### Isu D: Inertia.js Object/Enum Rendering (Vue Hydration Bug)
- **File Terdampak**: `app/Http/Controllers/UploadController.php`
- **Error yang Muncul**: Tombol pilihan model "Tipe Analisis" (`Penjualan`, `Keuangan`) dan "Tone Narasi" tampil **kosong** (tanpa teks label) pada *User Interface*.
- **Root Cause**: Controller PHP mem*passing* array dari PHP *Enum Constants* secara langsung ke komponen Vue melalui Inertia prop (`analysisTypes` & `tones`). Objek tipe *Enum* murni ini seringkali kurang kompatibel saat deserialisasi JSON di sisi Vue jika tidak dikonversi terlebih dahulu, sehingga properti `label` tidak dikenali oleh DOM.
- **Tindakan Perbaikan**:
  - Memetakan ulang ( *mapping* ) elemen *enum* menjadi format array asosiatif murni untuk Vue:
    ```php
    'analysisTypes' => collect(AnalysisType::cases())->map(fn ($case) => [
        'value' => $case->value,
        'label' => $case->label()
    ])->toArray(),
    ```

### Isu E: Vite Manifest SSR HMR / IPv6 Blocking
- **File Terdampak**: `vite.config.js` & `public/hot`
- **Error yang Muncul**: `Illuminate\Foundation\ViteManifestNotFoundException` (HTTP 500 saat me- *refresh* halaman secara paksa / *Ctrl+Shift+R*).
- **Root Cause**: Dev server Vite berjalan, namun file koneksinya (`public/hot`) mendaftarkan alamat IPv6 yaitu `http://[::1]:5173`. Framework Laravel (PHP) menolak/gagal me-resolve alamat referensi loopback IPv6 tersebut, sehingga berasumsi dev server Vite terhenti dan kemudian mencari `manifest.json` (yang juga tidak ada di *development mode*).
- **Tindakan Perbaikan**:
  - Menambahkan baris konfigurasi ke `vite.config.js`:
    ```javascript
    server: { host: 'localhost' }
    ```
  - Mematikan seluruh *instance node.js zombie* pada sistem dan me-*restart* `npm run dev`.
  - Hasilnya, URL pada `public/hot` dikembalikan ke *binding IPv4* standar (`http://localhost:5173`) sehingga *hot reload* Inertia.js berfungsi mulus tanpa melempar error HTTP 500.

---

## 4. Referensi Status Layanan Saat Ini
| Service | Environment | Port Aktif | Keterangan |
| :--- | :--- | :--- | :--- |
| **Laravel App** | Windows (PHP CLI) | `:8000` | Melayani UI Utama, API Routing, dan *Database* |
| **Vite Dev Server** | Windows (Node) | `:5173` | Melayani *Bundle Vue*, *Asset*, *Hot-Module Replacement* |
| **Queue Worker** | Background Job | - | Driver: `database`. Aktif memantau antrean *ProcessDataJob* |
| **Python Service** | Uvicorn (FastAPI) | `:8001` | Core *Data Cleansing* & AI Integration (*prompt_builder, analyzer*) |

Aplikasi kini sepenuhnya fungsional dan siap diuji performa pada alur *end-to-end* sesuai Prompt 14 (yaitu, verifikasi integrasi pengiriman tugas CSV ke Python Service).
