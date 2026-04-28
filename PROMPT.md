# PROMPT.md — DataNarasi Project
# Gunakan file ini sebagai master prompt di Claude Code (Cursor IDE)

## Identitas Project

Nama project  : DataNarasi
Tujuan        : Aplikasi web yang menerima upload CSV/Excel, membersihkan data
                secara otomatis menggunakan Python/Pandas, menganalisis statistik,
                lalu menghasilkan narasi insight bisnis dalam Bahasa Indonesia
                menggunakan multi-AI provider dengan sistem fallback otomatis.
Stack utama   : Laravel 12 + Inertia.js + Vue 3 + Tailwind CSS + MySQL + Redis
                Python 3.11 + FastAPI + Pandas + Matplotlib
                Multi-AI: Gemini Free → Kimi API → GLM → NVIDIA NIM → MiniMax → Claude API (fallback chain)
Deploy target : Railway (Laravel app + Python service sebagai dua service terpisah)

---

## Konteks Developer

- Developer menggunakan Windows 11, Cursor IDE, Claude Code
- Laravel project di: C:\Users\HP\Laravel\data-narasi
- Python service di subfolder: C:\Users\HP\Laravel\data-narasi\python-service
- Database: MySQL lokal untuk development, Railway MySQL untuk production
- Sudah familiar dengan Laravel, Vue 3, Tailwind, Pandas, Power BI
- Level: intermediate — boleh jelaskan konsep baru tapi jangan terlalu verbose

---

## Aturan Coding Wajib

### Umum
- Selalu tulis komentar dalam Bahasa Indonesia yang ringkas
- Nama variable/function dalam bahasa Inggris (snake_case untuk PHP/Python, camelCase untuk JS)
- Setiap file baru wajib ada docblock/docstring di atas
- Jangan hapus kode yang ada kecuali diminta eksplisit
- Selalu tunjukkan full file path di atas code block: `// app/Http/Controllers/UploadController.php`

### Laravel / PHP
- Gunakan Laravel 12 conventions (tidak ada legacy patterns)
- Model menggunakan Eloquent dengan proper relationships
- Validation menggunakan Form Request classes, bukan inline di controller
- Jobs menggunakan Laravel Queue (Redis driver)
- Semua response API menggunakan JsonResponse dengan struktur konsisten:
  { "success": bool, "data": any, "message": string }
- Gunakan PHP 8.2+ features: readonly properties, enums, match expressions
- Migrations wajib ada kolom: id, created_at, updated_at
- Enum status report: pending, processing, done, failed

### Vue 3 / Frontend
- Gunakan Composition API dengan <script setup> — tidak ada Options API
- State management: Pinia (bukan Vuex)
- Styling: Tailwind CSS utility classes saja — tidak ada inline style kecuali dynamic
- Komponen di resources/js/Components/
- Pages di resources/js/Pages/
- Gunakan Inertia.js untuk navigasi (bukan Vue Router)

### Python / FastAPI
- Semua kode di python-service/
- Gunakan type hints di semua function
- Pandas operations wajib handle exception (file corrupt, encoding salah, dll)
- Setiap provider AI punya class tersendiri yang inherit dari BaseAIProvider
- Log semua AI call ke file + kirim ke Laravel via HTTP callback

---

## Struktur Folder Target

```
data-narasi/
├── app/
│   ├── Enums/
│   │   ├── ReportStatus.php
│   │   └── AnalysisType.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── UploadController.php
│   │   │   ├── ReportController.php
│   │   │   └── Admin/AIProviderController.php
│   │   └── Requests/
│   │       └── UploadFileRequest.php
│   ├── Jobs/
│   │   └── ProcessDataJob.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Report.php
│   │   ├── ReportColumn.php
│   │   ├── AIProvider.php
│   │   ├── AIUsageLog.php
│   │   └── ReportOutput.php
│   └── Services/
│       └── PythonServiceClient.php
├── database/
│   └── migrations/
│       ├── create_reports_table.php
│       ├── create_report_columns_table.php
│       ├── create_ai_providers_table.php
│       ├── create_ai_usage_logs_table.php
│       └── create_report_outputs_table.php
├── python-service/
│   ├── main.py              ← FastAPI entrypoint
│   ├── cleaner.py           ← Pandas data cleansing
│   ├── analyzer.py          ← Statistik & tren
│   ├── chart_generator.py   ← Matplotlib chart output
│   ├── prompt_builder.py    ← Rakit prompt per tipe analisis
│   ├── ai_provider.py       ← Multi-AI fallback manager
│   ├── providers/
│   │   ├── base.py
│   │   ├── gemini.py
│   │   ├── kimi.py
│   │   ├── glm.py
│   │   ├── nvidia.py
│   │   ├── minimax.py
│   │   └── claude.py
│   └── requirements.txt
├── resources/
│   └── js/
│       ├── Components/
│       │   ├── UploadZone.vue
│       │   ├── ProgressTracker.vue
│       │   ├── NarrativeBox.vue
│       │   ├── StatGrid.vue
│       │   └── AIProviderToggle.vue
│       └── Pages/
│           ├── Upload.vue
│           ├── Processing.vue
│           ├── Report/Show.vue
│           └── Admin/AIProviders.vue
├── docs/
│   ├── ARCHITECTURE.md
│   ├── API.md
│   └── PROMPT_ENGINEERING.md
├── .env.example
├── PROMPT.md               ← file ini
└── README.md
```

---

## Urutan Pengerjaan (Build Order)

Ikuti urutan ini agar tidak ada dependency yang belum tersedia:

1. [ ] Setup Laravel project (composer, .env, database)
2. [ ] Buat semua migrations dan jalankan migrate
3. [ ] Buat Enums (ReportStatus, AnalysisType)
4. [ ] Buat semua Models dengan relationships
5. [ ] Buat Form Request (UploadFileRequest)
6. [ ] Buat Controllers (Upload, Report, Admin/AIProvider)
7. [ ] Setup Queue (Redis) + buat ProcessDataJob
8. [ ] Setup Python FastAPI service (main.py + requirements.txt)
9. [ ] Buat cleaner.py (Pandas pipeline)
10. [ ] Buat analyzer.py (statistik)
11. [ ] Buat ai_provider.py + semua provider classes
12. [ ] Buat prompt_builder.py
13. [ ] Buat chart_generator.py
14. [ ] Setup Inertia.js + Vue 3 di Laravel
15. [ ] Buat semua Vue components
16. [ ] Buat semua Vue pages
17. [ ] Testing end-to-end
18. [ ] Buat README + deploy config Railway

---

## Environment Variables yang Dibutuhkan

```env
# Laravel
APP_NAME=DataNarasi
APP_ENV=local
DB_CONNECTION=mysql
DB_DATABASE=data_narasi
QUEUE_CONNECTION=redis

# Python Service URL (internal)
PYTHON_SERVICE_URL=http://localhost:8001

# AI Providers
GEMINI_API_KEY=
KIMI_API_KEY=
GLM_API_KEY=
NVIDIA_API_KEY=
MINIMAX_API_KEY=
CLAUDE_API_KEY=

# AI Provider Order (koma-separated, sesuai priority)
AI_PROVIDER_ORDER=gemini,kimi,glm,nvidia,minimax,claude

# Storage
FILESYSTEM_DISK=local
MAX_UPLOAD_SIZE_MB=10
```

---

## Cara Pakai File Ini di Claude Code

Saat memulai sesi baru di Claude Code, ketik:
```
Baca PROMPT.md dulu, lalu kita lanjut dari step [nomor] di Build Order.
```

Atau untuk task spesifik:
```
Berdasarkan PROMPT.md, buatkan [nama file] sesuai aturan yang ada.
```
