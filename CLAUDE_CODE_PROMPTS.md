# CLAUDE_CODE_PROMPTS.md
# Kumpulan prompt siap pakai untuk Claude Code di Cursor IDE
# Copy-paste satu per satu secara urut — tunggu selesai sebelum lanjut ke prompt berikutnya

---

## ========================
## PROMPT 01 — ORIENTASI AWAL
## ========================
## Jalankan ini PERTAMA KALI di setiap sesi Claude Code baru

```
Baca file PROMPT.md, SKILL.md, .cursorrules, dan .claude/CLAUDE.md yang ada di root project ini.
Setelah membaca, konfirmasi dengan merangkum:
1. Nama project dan tujuannya
2. Tech stack yang digunakan
3. Step berapa yang akan kita kerjakan sekarang
Jangan mulai coding dulu sebelum konfirmasi ini.
```

---

## ========================
## PROMPT 02 — SETUP LARAVEL BARU
## ========================
## Gunakan ini kalau folder masih kosong / belum ada Laravel

```
Kita mulai dari Step 1 Build Order di PROMPT.md.

Tolong jalankan perintah-perintah berikut secara urut di terminal:
1. composer create-project laravel/laravel . --prefer-dist
   (install Laravel 12 di folder ini, titik berarti folder saat ini)
2. php artisan key:generate
3. composer require inertiajs/inertia-laravel tightenco/ziggy
4. npm install
5. npm install vue@3 @inertiajs/vue3 @vitejs/plugin-vue pinia @headlessui/vue

Setelah selesai, tampilkan output dari: php artisan --version
```

---

## ========================
## PROMPT 03 — SETUP FILE KONFIGURASI
## ========================

```
Lanjut setup konfigurasi awal. Kerjakan semua ini:

1. Update file vite.config.js agar support Vue 3 + Inertia:
   - Tambahkan plugin vue()
   - Set input ke resources/js/app.js dan resources/css/app.css

2. Update resources/js/app.js untuk bootstrap Inertia + Vue 3:
   - Import createApp dari vue
   - Import createInertiaApp dari @inertiajs/vue3
   - Setup resolveComponent dengan glob import dari Pages/

3. Update resources/views/app.blade.php (buat kalau belum ada):
   - Template HTML dasar dengan @inertiaHead dan @inertia directive
   - Include @vite(['resources/css/app.css', 'resources/js/app.js'])

4. Install Tailwind CSS:
   - npm install -D tailwindcss postcss autoprefixer
   - npx tailwindcss init -p
   - Update tailwind.config.js content paths untuk .vue dan .blade.php
   - Tambahkan @tailwind directives ke resources/css/app.css

Tampilkan setiap file yang dibuat/diubah beserta full path-nya.
```

---

## ========================
## PROMPT 04 — BUAT SEMUA MIGRATIONS
## ========================

```
Buat semua migration file untuk DataNarasi sesuai skema di PROMPT.md.
Buat dalam urutan ini (penting karena ada foreign key):

1. Migration: create_ai_providers_table
   Kolom: id, name (string), model_id (string), base_url (string),
   api_key_env (string), priority (integer default 1),
   is_enabled (boolean default true), max_tokens (integer default 1024),
   timeout_seconds (integer default 30), total_calls (integer default 0),
   total_errors (integer default 0), last_used_at (timestamp nullable),
   timestamps()

2. Migration: create_reports_table
   Kolom: id, user_id (foreignId constrained users cascadeOnDelete),
   title (string nullable), original_filename (string),
   original_path (string), clean_path (string nullable),
   status (enum: pending,processing,done,failed — default pending),
   dataset_type (string default 'umum'),
   total_rows (integer default 0), clean_rows (integer default 0),
   summary_stats (json nullable), cleaning_log (json nullable),
   chart_paths (json nullable), ai_narrative (text nullable),
   ai_provider_used (string nullable), processing_time_ms (integer nullable),
   timestamps()

3. Migration: create_report_columns_table
   Kolom: id, report_id (foreignId constrained reports cascadeOnDelete),
   column_name (string), data_type (string),
   null_count (integer default 0), unique_count (integer default 0),
   min_value (decimal nullable), max_value (decimal nullable),
   mean_value (decimal nullable), sample_values (json nullable),
   is_numeric (boolean default false), has_outliers (boolean default false),
   timestamps()

4. Migration: create_ai_usage_logs_table
   Kolom: id, report_id (foreignId constrained reports cascadeOnDelete),
   provider_id (foreignId constrained ai_providers),
   status (enum: success,rate_limit,timeout,auth_error,validation_fail,error),
   attempt_number (integer default 1), tokens_used (integer nullable),
   response_time_ms (integer nullable), error_message (text nullable),
   timestamps()

5. Migration: create_report_outputs_table
   Kolom: id, report_id (foreignId constrained reports cascadeOnDelete),
   output_type (enum: pdf,excel,chart_png,json_api,share_page),
   file_path (string nullable), public_url (string nullable),
   share_token (string nullable unique), is_public (boolean default false),
   download_count (integer default 0), expires_at (timestamp nullable),
   timestamps()

Setelah semua migration dibuat, jalankan: php artisan migrate
Tampilkan hasil migration.
```

---

## ========================
## PROMPT 05 — BUAT ENUMS
## ========================

```
Buat PHP Enums di app/Enums/ untuk DataNarasi:

1. app/Enums/ReportStatus.php
   - Backed enum string
   - Cases: Pending = 'pending', Processing = 'processing',
     Done = 'done', Failed = 'failed'
   - Tambahkan method label(): string yang return label Bahasa Indonesia
     (Menunggu, Sedang diproses, Selesai, Gagal)
   - Tambahkan method color(): string untuk Tailwind badge class

2. app/Enums/AnalysisType.php
   - Backed enum string
   - Cases: Penjualan = 'penjualan', Keuangan = 'keuangan',
     Operasional = 'operasional', Marketing = 'marketing',
     Inventori = 'inventori', Umum = 'umum'
   - Tambahkan method label(): string (label display)
   - Tambahkan method emphasis(): string (fokus analisis untuk prompt AI)

3. app/Enums/NarrativeTone.php
   - Backed enum string
   - Cases: Formal = 'formal', Santai = 'santai', Teknis = 'teknis'
   - Tambahkan method label(): string
   - Tambahkan method instruction(): string (instruksi tone untuk prompt AI)

Tampilkan semua file lengkap.
```

---

## ========================
## PROMPT 06 — BUAT SEMUA MODELS
## ========================

```
Buat semua Eloquent Models untuk DataNarasi di app/Models/:

1. app/Models/AIProvider.php
   - fillable: name, model_id, base_url, api_key_env, priority,
     is_enabled, max_tokens, timeout_seconds
   - Cast: is_enabled → boolean
   - HasMany: aiUsageLogs()
   - Scope: scopeEnabled() — filter is_enabled = true, order by priority
   - Method: getApiKey(): ?string — ambil nilai env berdasarkan api_key_env

2. app/Models/Report.php
   - fillable: semua kolom kecuali id dan timestamps
   - Cast: status → ReportStatus enum, summary_stats → array,
     cleaning_log → array, chart_paths → array
   - BelongsTo: user()
   - HasMany: columns(), usageLogs(), outputs()
   - Scope: scopeForUser($userId)
   - Method: isProcessing(): bool, isDone(): bool, isFailed(): bool

3. app/Models/ReportColumn.php
   - fillable: semua kolom kecuali id
   - Cast: sample_values → array, is_numeric → boolean, has_outliers → boolean
   - BelongsTo: report()

4. app/Models/AIProvider.php (sudah di atas)

5. app/Models/AIUsageLog.php
   - fillable: semua kolom kecuali id
   - Cast: status → string
   - BelongsTo: report(), provider() → AIProvider

6. app/Models/ReportOutput.php
   - fillable: semua kolom kecuali id
   - Cast: is_public → boolean
   - BelongsTo: report()
   - Method: generateShareToken(): string — buat random token 8 karakter

Tampilkan semua file lengkap dengan docblock.
```

---

## ========================
## PROMPT 07 — BUAT FORM REQUESTS & CONTROLLERS
## ========================

```
Buat Form Request dan Controllers untuk DataNarasi:

1. app/Http/Requests/UploadFileRequest.php
   - Rules:
     file: required, file, mimes:csv,xlsx,xls, max:10240 (10MB)
     analysis_type: required, in: (semua cases AnalysisType enum)
     tone: required, in: (semua cases NarrativeTone enum)
     title: nullable, string, max:255
   - Messages dalam Bahasa Indonesia
   - authorize(): return true (auth check di route middleware)

2. app/Http/Controllers/UploadController.php
   - Method store(UploadFileRequest $request):
     a. Simpan file ke storage/app/uploads/{user_id}/
     b. Buat record Report (status: pending)
     c. Dispatch ProcessDataJob dengan delay 0
     d. Return Inertia redirect ke /reports/{id}/processing

3. app/Http/Controllers/ReportController.php
   - Method index(): tampilkan semua report milik user (Inertia)
   - Method show(Report $report): tampilkan hasil report (Inertia)
   - Method processing(Report $report): halaman loading/progress (Inertia)
   - Method status(Report $report): return JSON status untuk polling
   - Method destroy(Report $report): hapus report + file terkait

4. app/Http/Controllers/Admin/AIProviderController.php
   - Method index(): tampilkan semua provider + stats hari ini (Inertia)
   - Method toggle(AIProvider $provider): toggle is_enabled, return JSON
   - Method updatePriority(Request $request): update urutan priority

Gunakan route model binding. Tambahkan policy check: user hanya bisa
akses report miliknya sendiri.
```

---

## ========================
## PROMPT 08 — BUAT JOB & SERVICE
## ========================

```
Buat Queue Job dan Service class:

1. app/Jobs/ProcessDataJob.php
   - implements ShouldQueue
   - public $timeout = 300 (5 menit)
   - public $tries = 1 (tidak auto retry)
   - Constructor: terima $reportId (int)
   - Method handle():
     a. Ambil Report dari DB, update status → processing
     b. Buat instance PythonServiceClient
     c. Kirim request ke Python service
     d. Kalau exception: update status → failed, log error
   - Method failed(Throwable $e):
     Update report status → failed
     Log error ke storage/logs/

2. app/Services/PythonServiceClient.php
   - Constructor: baca PYTHON_SERVICE_URL dan PYTHON_SERVICE_SECRET dari env
   - Method process(Report $report): void
     POST ke {PYTHON_SERVICE_URL}/process
     Payload JSON:
     {
       "report_id": int,
       "file_path": string,
       "analysis_type": string,
       "tone": string,
       "callback_url": url('/api/v1/reports/{id}/callback'),
       "callback_secret": PYTHON_SERVICE_SECRET
     }
     Timeout: 10 detik untuk kirim request (Python akan callback saat selesai)
   - Method handleCallback(array $data): void
     Terima data dari Python, update Report model

3. app/Http/Controllers/Api/ReportCallbackController.php
   - Method __invoke(Request $request, Report $report):
     a. Validasi callback_secret header
     b. Update report: ai_narrative, summary_stats, chart_paths,
        clean_path, clean_rows, ai_provider_used, processing_time_ms
     c. Update status → done (atau failed kalau ada error flag)
     d. Simpan ReportOutput records untuk setiap output file
     e. Return response 200

Tampilkan semua file lengkap.
```

---

## ========================
## PROMPT 09 — BUAT ROUTES
## ========================

```
Setup semua routes di routes/web.php dan routes/api.php:

routes/web.php:
- GET  /                     → redirect ke /upload (atau landing page)
- GET  /upload               → UploadController@create (Inertia)
- POST /upload               → UploadController@store
- GET  /reports              → ReportController@index (Inertia)
- GET  /reports/{report}     → ReportController@show (Inertia)
- GET  /reports/{report}/processing → ReportController@processing (Inertia)
- GET  /reports/{report}/status    → ReportController@status (JSON polling)
- DELETE /reports/{report}   → ReportController@destroy
- GET  /admin/ai-providers   → Admin\AIProviderController@index (Inertia)
- PATCH /admin/ai-providers/{provider}/toggle → Admin\AIProviderController@toggle
- POST /admin/ai-providers/priority → Admin\AIProviderController@updatePriority

Semua route di atas: middleware(['auth', 'verified'])

routes/api.php:
- POST /v1/reports/{report}/callback → Api\ReportCallbackController

Setelah routes dibuat, jalankan: php artisan route:list
Tampilkan outputnya.
```

---

## ========================
## PROMPT 10 — BUAT SEEDER AI PROVIDERS
## ========================

```
Buat database seeder untuk data awal AI Providers:

1. database/seeders/AIProviderSeeder.php
   Insert 4 records ke tabel ai_providers:

   Record 1:
   - name: 'Gemini 1.5 Flash'
   - model_id: 'gemini-1.5-flash'
   - base_url: 'https://generativelanguage.googleapis.com'
   - api_key_env: 'GEMINI_API_KEY'
   - priority: 1
   - is_enabled: true
   - max_tokens: 1024
   - timeout_seconds: 30

   Record 2:
   - name: 'Kimi (Moonshot)'
   - model_id: 'moonshot-v1-8k'
   - base_url: 'https://api.moonshot.cn/v1'
   - api_key_env: 'KIMI_API_KEY'
   - priority: 2
   - is_enabled: true

   Record 3:
   - name: 'GLM-4 Flash'
   - model_id: 'glm-4-flash'
   - base_url: 'https://open.bigmodel.cn/api/paas/v4'
   - api_key_env: 'GLM_API_KEY'
   - priority: 3
   - is_enabled: true

   Record 4:
   - name: 'Claude (Anthropic)'
   - model_id: 'claude-sonnet-4-20250514'
   - base_url: 'https://api.anthropic.com'
   - api_key_env: 'CLAUDE_API_KEY'
   - priority: 4
   - is_enabled: false

2. Daftarkan AIProviderSeeder di DatabaseSeeder.php
3. Jalankan: php artisan db:seed --class=AIProviderSeeder
4. Verifikasi dengan: php artisan tinker → AIProvider::all()
```

---

## ========================
## PROMPT 11 — BUAT VUE LAYOUT & KOMPONEN
## ========================

```
Buat layout dan komponen Vue 3 dasar:

1. resources/js/Layouts/AppLayout.vue
   - Topbar dengan logo "DataNarasi" (teal accent pada "Narasi")
   - Nav links: Upload, Riwayat, Admin (hanya tampil untuk role admin)
   - Avatar user di kanan (initials dari nama user)
   - Slot default untuk konten halaman
   - Gunakan Tailwind, warna aksen: teal-600 (#0F6E56)

2. resources/js/Components/UploadZone.vue
   - Props: modelValue (File|null), accept (string), maxSizeMb (number)
   - Emit: update:modelValue
   - Tampilan: dashed border zone, icon upload SVG, teks instruksi
   - Handle drag & drop
   - Validasi tipe file dan ukuran di sisi client
   - Preview nama file kalau sudah dipilih

3. resources/js/Components/StatGrid.vue
   - Props: stats (array of { label, value, prefix?, suffix? })
   - Tampilkan sebagai grid 2x2 atau 4 kolom
   - Style metric card: bg-gray-50, nilai besar font-medium

4. resources/js/Components/NarrativeBox.vue
   - Props: narrative (string), providerUsed (string), isLoading (boolean)
   - Tampilkan narasi dengan line-height longgar
   - Badge kecil di pojok kanan atas: nama AI yang dipakai
   - Loading skeleton saat isLoading = true

5. resources/js/Components/ProgressTracker.vue
   - Props: steps (array of { name, description, status: 'done'|'active'|'waiting' })
   - Tampilkan setiap step dengan icon status, nama, deskripsi
   - Step aktif: progress bar di bawahnya
   - Koneksi visual antar step (garis vertikal)

Tampilkan semua file lengkap.
```

---

## ========================
## PROMPT 12 — BUAT VUE PAGES
## ========================

```
Buat semua halaman Vue Inertia:

1. resources/js/Pages/Upload.vue
   - Import dan gunakan UploadZone, AppLayout
   - Form: UploadZone + pilihan tipe analisis (6 option cards) +
     dropdown tone narasi + input judul (opsional)
   - Submit via useForm() dari @inertiajs/vue3
   - Disable tombol submit saat form.processing
   - Tampilkan error validasi di bawah field masing-masing

2. resources/js/Pages/Report/Processing.vue
   - Props: report (object dengan id, status, original_filename)
   - Polling setiap 3 detik ke /reports/{id}/status via axios
   - Tampilkan ProgressTracker dengan 5 steps
   - Update step status berdasarkan response polling
   - Auto redirect ke /reports/{id} saat status = 'done'
   - Tampilkan pesan error + tombol retry saat status = 'failed'
   - Tampilkan nama AI yang sedang aktif

3. resources/js/Pages/Report/Show.vue
   - Props: report (object lengkap dengan narrative, stats, chart_paths, outputs)
   - StatGrid di atas (4 metric cards dari summary_stats)
   - NarrativeBox dengan narasi AI
   - Section chart: tampilkan chart_paths sebagai <img>
   - Section download: list ReportOutputs dengan tombol unduh/salin link
   - Badge di header: nama AI yang dipakai + status Done

4. resources/js/Pages/Report/Index.vue
   - Props: reports (paginated collection)
   - Tabel/card list semua report milik user
   - Kolom: judul, tipe, tanggal, status badge, aksi (lihat, hapus)
   - Tombol "Upload Baru" di atas

5. resources/js/Pages/Admin/AIProviders.vue
   - Props: providers (array), todayStats (object)
   - StatGrid: total request, success rate, fallback count, provider aktif
   - List provider: nama, model, toggle on/off, jumlah call, jumlah error
   - Section log fallback terbaru (5 terakhir)

Gunakan <script setup lang="ts"> di semua file.
Tampilkan semua file lengkap.
```

---

## ========================
## PROMPT 13 — SETUP PYTHON SERVICE
## ========================

```
Buat semua file Python service di folder python-service/:

1. python-service/main.py
   FastAPI app dengan endpoints:
   - GET  /health → return {"status": "ok", "timestamp": now}
   - POST /process → terima ProcessRequest (Pydantic model), jalankan pipeline

   ProcessRequest model:
   { report_id, file_path, analysis_type, tone, callback_url, callback_secret }

   Pipeline di /process (jalankan async):
   a. cleaner.run(file_path) → clean_result
   b. analyzer.run(clean_result['df'], analysis_type) → stats
   c. chart_generator.run(clean_result['df'], stats, report_id) → chart_paths
   d. prompt_builder.build(stats, meta, tone) → prompt
   e. ai_provider.generate(prompt) → narrative
   f. Callback ke Laravel dengan semua hasil

2. python-service/cleaner.py
   Function run(file_path: str) -> dict:
   - Deteksi encoding dengan chardet
   - Baca CSV atau Excel berdasarkan ekstensi
   - Pipeline cleansing 8 langkah (lihat SKILL.md)
   - Return: { 'df': DataFrame, 'cleaning_log': dict }

3. python-service/analyzer.py
   Function run(df: DataFrame, analysis_type: str) -> dict:
   - Hitung: total_revenue (jika ada kolom harga/total),
     total_transactions (jumlah baris), avg_transaction,
     growth_rate (bandingkan paruh pertama vs paruh kedua data),
     top_products (group by kolom produk jika ada, top 5),
     outliers (nilai > mean + 3*std),
     peak_period (periode dengan nilai tertinggi),
     trend_direction (regresi linear sederhana)
   - Return dict statistik

4. python-service/chart_generator.py
   Function run(df: DataFrame, stats: dict, report_id: int) -> list[str]:
   - Buat folder storage/charts/{report_id}/
   - Generate: line chart tren (jika ada kolom tanggal),
     bar chart top produk (jika ada),
     simpan sebagai PNG 800x400px
   - Return list path file chart

5. python-service/providers/base.py
   Abstract class BaseAIProvider:
   - Abstract method generate(prompt: str, system: str, max_tokens: int) -> str
   - Method validate_narrative(text: str) -> bool (cek 5 kondisi dari SKILL.md)

6. python-service/providers/gemini.py
   Class GeminiProvider(BaseAIProvider):
   - Gunakan google-generativeai SDK
   - Handle RateLimitError, TimeoutError

7. python-service/providers/kimi.py
   Class KimiProvider(BaseAIProvider):
   - Gunakan openai SDK dengan base_url Moonshot
   - Handle RateLimitError, TimeoutError

8. python-service/providers/glm.py
   Class GLMProvider(BaseAIProvider):
   - Gunakan zhipuai SDK
   - Tambahkan instruksi Bahasa Indonesia eksplisit di prompt
   - Handle RateLimitError, TimeoutError

9. python-service/providers/claude.py
   Class ClaudeProvider(BaseAIProvider):
   - Gunakan anthropic SDK
   - Handle RateLimitError, TimeoutError

10. python-service/ai_provider.py
    Class AIProviderManager:
    - Load config providers dari env AI_PROVIDER_ORDER
    - Method generate(prompt, system, max_tokens) -> dict:
      Loop provider chain, coba satu per satu
      Return { narrative, provider_used, attempts, success }
    - Fallback ke template statis kalau semua gagal

11. python-service/prompt_builder.py
    Function build(stats: dict, meta: dict, tone: str) -> tuple[str, str]:
    - Return (system_prompt, user_prompt) sesuai struktur 4-layer di SKILL.md
    - Sesuaikan per analysis_type menggunakan ANALYSIS_FOCUS dict

Tampilkan semua file lengkap dengan type hints dan docstrings.
```

---

## ========================
## PROMPT 14 — TESTING END TO END
## ========================

```
Lakukan testing end-to-end DataNarasi:

1. Pastikan semua service berjalan:
   - Laravel: php artisan serve (port 8000)
   - Queue worker: php artisan queue:work redis
   - Python service: cd python-service && uvicorn main:app --port 8001
   - Cek Python health: curl http://localhost:8001/health

2. Test upload flow:
   - Buat file CSV test sederhana (10 baris data penjualan fiktif)
   - Upload via halaman /upload
   - Pantau queue job: php artisan queue:monitor
   - Cek Python service menerima request (lihat log uvicorn)
   - Verifikasi callback diterima Laravel

3. Cek database setelah proses selesai:
   - php artisan tinker
   - Report::latest()->first()->status
   - Report::latest()->first()->ai_narrative
   - Report::latest()->first()->ai_provider_used

4. Kalau ada error, tampilkan:
   - storage/logs/laravel.log (10 baris terakhir)
   - Output console Python service

Laporkan hasil setiap langkah.
```

---

## ========================
## PROMPT 15 — DEPLOY KE RAILWAY
## ========================

```
Siapkan konfigurasi deployment untuk Railway:

1. Buat railway.json di root project:
   {
     "build": { "builder": "nixpacks" },
     "deploy": {
       "startCommand": "php artisan config:cache && php artisan route:cache && php artisan migrate --force && php artisan serve --host 0.0.0.0 --port $PORT"
     }
   }

2. Buat python-service/railway.json:
   {
     "build": { "builder": "nixpacks" },
     "deploy": {
       "startCommand": "uvicorn main:app --host 0.0.0.0 --port $PORT"
     }
   }

3. Buat python-service/nixpacks.toml:
   [phases.setup]
   nixPkgs = ["python311"]

4. Update .env.example dengan catatan Railway:
   Tambahkan komentar bahwa DATABASE_URL dan REDIS_URL diisi otomatis Railway

5. Buat Procfile di root (untuk queue worker di Railway):
   web: php artisan serve --host 0.0.0.0 --port $PORT
   worker: php artisan queue:work redis --sleep=3 --tries=1

6. Pastikan .gitignore sudah include:
   .env, storage/app/uploads/*, python-service/.venv/,
   python-service/__pycache__/, storage/charts/

Tampilkan semua file konfigurasi deployment.
```

---

## ========================
## PROMPT BONUS — FIX ERROR UMUM
## ========================

### Kalau ada error "Class not found":
```
Jalankan: composer dump-autoload
Lalu cek apakah namespace di file [sebutkan nama file] sudah benar
sesuai dengan struktur folder app/[subfolder]/.
```

### Kalau Queue Job tidak jalan:
```
Cek konfigurasi:
1. php artisan config:clear && php artisan config:cache
2. Pastikan QUEUE_CONNECTION=redis di .env
3. Pastikan Redis berjalan: redis-cli ping (harus return PONG)
4. Restart queue worker: php artisan queue:restart
5. Lihat failed jobs: php artisan queue:failed
```

### Kalau Inertia blank page:
```
Cek:
1. npm run dev sudah berjalan?
2. Buka browser console — ada error JavaScript?
3. Pastikan resources/views/app.blade.php ada @inertiaHead dan @inertia
4. Pastikan app.js sudah createInertiaApp dengan resolveComponent yang benar
5. Jalankan: php artisan inertia:middleware
```

### Kalau Python service tidak bisa dipanggil dari Laravel:
```
Cek:
1. PYTHON_SERVICE_URL di .env sudah benar (http://localhost:8001)
2. Python uvicorn sudah berjalan di port 8001
3. Test manual: curl -X POST http://localhost:8001/health
4. Cek firewall Windows tidak blokir port 8001
```

### Kalau AI return bahasa Mandarin (GLM):
```
Di python-service/providers/glm.py, pastikan ada instruksi eksplisit:
"PENTING: Jawab HANYA dalam Bahasa Indonesia. Jangan gunakan bahasa lain."
Instruksi ini harus ada di AWAL user message, bukan di akhir.
```
