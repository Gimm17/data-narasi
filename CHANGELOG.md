# Release Notes

## [2026-04-28 v3] - Expanded Upload Options & Dynamic Provider Order

### 📋 Upload Options Expansion

- **Analysis Types (6 → 11):** Added 5 new domain-specific analysis types:
  - 👥 **SDM / HR** — Kinerja karyawan, turnover, absensi, demografi SDM
  - 🎓 **Akademik** — Nilai, distribusi grade, kelulusan, tren per mata pelajaran
  - 🏥 **Kesehatan** — Data pasien, prevalensi penyakit, indikator klinis
  - 🚚 **Logistik** — Efisiensi pengiriman, lead time, biaya transportasi
  - 📋 **Survey / Polling** — Distribusi jawaban, sentimen, korelasi variabel
- **Narrative Tones (3 → 6):** Added 3 new writing styles:
  - 📌 **Eksekutif** — Ringkasan KPI 1 menit, bullet points, rekomendasi strategis
  - 📖 **Storytelling** — Data diceritakan seperti narasi yang mengalir
  - 🎓 **Akademis** — Gaya ilmiah untuk skripsi, jurnal, dan laporan penelitian
- Each option now displays **icon + label + description** in a visual card grid.
- Python `prompt_builder.py` updated with domain-specific metrics and business questions for all 11 types and tone instructions for all 6 tones.

### 🔧 Dynamic Provider Order Fix

- **Admin panel priority now actually controls AI fallback order.** Previously, admin panel updated DB but Python read from `.env` (disconnect).
- Laravel now sends `provider_order` (from `ai_providers` table sorted by `priority ASC`) in every `/process` request.
- Python `AIProviderManager.generate()` accepts dynamic `provider_order` override.
- Uses `api_key_env` → slug mapping for reliable provider identification.

### UI Improvements

- Upload page redesigned: tone selector changed from dropdown to visual card grid (same style as analysis types).
- Submit button now shows animated spinner during upload.
- Reset button properly resets both visual state and form data.

### Modified Files
- `app/Enums/AnalysisType.php` — 5 new enum cases + icon/description
- `app/Enums/NarrativeTone.php` — 3 new enum cases + icon/description
- `app/Http/Controllers/UploadController.php` — Pass icon + description to frontend
- `app/Services/PythonServiceClient.php` — Send DB provider order in /process payload
- `python-service/prompt_builder.py` — New analysis focus entries + tone instructions
- `python-service/ai_provider.py` — Dynamic provider_order param in generate()
- `python-service/main.py` — provider_order field in ProcessRequest
- `resources/js/Pages/Upload.vue` — Card-based UI for all options

---

## [2026-04-28 v2] - Security, UI Overhaul & Admin Panel Enhancement

### 🔒 Security Improvements

- **Config Cache Safety:** Migrated all `env()` calls in `PythonServiceClient` to `config()` via new `config/python-service.php`. Prevents `null` values after `php artisan config:cache` in production.
- **HMAC Callback Signing:** Added HMAC-SHA256 signature generation and validation for Python→Laravel callbacks. Both sides now sign/verify request payloads using shared secret, preventing tampered or spoofed callbacks. Backward-compatible with simple token validation as fallback.
- **CORS Restriction:** Replaced wildcard `allow_origins=["*"]` in Python FastAPI CORS middleware with configurable `ALLOWED_ORIGINS` env variable. Default: `http://localhost:8000`.
- **Admin Role-Based Access:** Added `is_admin` boolean column to `users` table with migration, `IsAdmin` middleware, and route guard on all `/admin/*` routes. Non-admin users now get 403 Forbidden.

### 🎨 Report Show Page — Complete Redesign

- **Interactive Charts:** Installed `chart.js` + `vue-chartjs`. Added `InteractiveChart.vue` component supporting Bar, Doughnut, and Line charts with hover tooltips, animated transitions, and responsive layout. Charts auto-build from `summary_stats` data (top products, revenue distribution, mean vs median).
- **Data Quality Card:** New `DataQualityCard.vue` with SVG circular progress visualization showing clean data percentage, color-coded quality rating, and cleaning log breakdown (duplicates, null rows, encoding fixes).
- **Stats Detail Panel:** New collapsible `StatsDetailPanel.vue` showing per-column statistics table for numeric columns (min, max, mean, median, std dev, null count) and text column cards with sample values and dtype badges.
- **Insight & Recommendations:** New `InsightRecommendation.vue` auto-generating business insights from summary_stats — growth rate trends, top product performers, optimization suggestions, revenue totals, and data quality warnings.
- **Narrative Box Upgrade:** Redesigned `NarrativeBox.vue` with copy-to-clipboard, print-to-new-window, word count display, and per-provider color-coded badges (Gemini=blue, Kimi=violet, GLM=orange, NVIDIA=green, MiniMax=pink, Claude=amber).
- **Tab Switcher:** Interactive Chart.js charts and static Matplotlib chart images accessible via tab toggle.
- **Quick Stats Row:** 4 metric cards — Total Baris, Baris Bersih, Kualitas Data (color-coded %), Waktu Proses.

### ⚙️ Admin Panel — Bug Fix & Feature Enhancement

- **Bug Fix:** Fixed `Call to a member function toISOString() on string` error by adding `'last_used_at' => 'datetime'` cast to `AIProvider` model.
- **Edit Modal:** New modal for editing API key value (stored securely in `.env`), model ID, max tokens, timeout, and priority per provider. API key values are never displayed in the UI — input uses password field.
- **Drag-to-Reorder:** Providers can be reordered by dragging rows in the table, updating priority via `POST /admin/ai-providers/priority`.
- **Error Log Section:** New section showing 5 most recent fallback errors with provider name, report ID, error message, and timestamp.
- **Redesigned Table:** Cleaner layout with priority numbers, model ID badges, compact toggle switches, and icon-based action buttons (gear for edit, refresh for reset).

### 🏗️ Architecture Improvements

- **Callback Retry Mechanism:** Python service now retries failed callbacks with exponential backoff (3 attempts: 2s, 4s, 8s). Applies to both success and error callbacks, preventing data loss if Laravel is temporarily down.
- **File Cleanup Command:** New `php artisan app:cleanup-uploads` command to delete upload files from completed/failed reports older than configurable days (default: 30). Supports `--dry-run` for preview and shows progress bar + summary table.
- **Better Token Estimation:** Replaced naive `len(narrative)` character count with word-based estimation (`len(text.split()) * 1.3`), significantly more accurate for Bahasa Indonesia/English tokenization.

### 🚀 Developer Experience

- **Single Command Startup:** `composer run dev` now runs all 4 services simultaneously via `concurrently`: Laravel server, Queue worker, Vite HMR, and Python uvicorn. Replaced `php artisan pail` (problematic on Windows) with Python service.
- **README Update:** Added "Quick Start" section with `composer run dev` as primary method, service table with color codes and URLs, collapsible manual terminal instructions.

### New Files
- `config/python-service.php` — Centralized Python service configuration
- `app/Http/Middleware/IsAdmin.php` — Admin role gate middleware
- `app/Console/Commands/CleanupOldUploads.php` — Artisan cleanup command
- `database/migrations/2026_04_28_043000_add_is_admin_to_users_table.php` — Admin column migration
- `resources/js/Components/InteractiveChart.vue` — Chart.js wrapper component
- `resources/js/Components/DataQualityCard.vue` — Data quality SVG visualization
- `resources/js/Components/StatsDetailPanel.vue` — Collapsible column stats panel
- `resources/js/Components/InsightRecommendation.vue` — Auto-generated business insights

### Modified Files
- `app/Services/PythonServiceClient.php` — env()→config(), HMAC signing
- `app/Http/Controllers/Api/ReportCallbackController.php` — HMAC validation
- `app/Http/Controllers/Admin/AIProviderController.php` — Edit provider, .env update
- `app/Models/AIProvider.php` — datetime cast, expanded fillable
- `app/Models/User.php` — is_admin field + isAdmin() method
- `bootstrap/app.php` — Admin middleware alias registration
- `routes/web.php` — Admin middleware + PUT route for provider update
- `database/seeders/DatabaseSeeder.php` — is_admin flag for test user
- `python-service/main.py` — HMAC signing, CORS restriction, callback retry
- `python-service/ai_provider.py` — Word-based token estimation
- `resources/js/Pages/Report/Show.vue` — Complete page rebuild
- `resources/js/Pages/Admin/AIProviders.vue` — Redesigned with edit modal + drag reorder
- `resources/js/Components/NarrativeBox.vue` — Copy/print/word count
- `.env` / `.env.example` — ALLOWED_ORIGINS, PYTHON_SERVICE_TIMEOUT
- `composer.json` — Python uvicorn in dev script
- `package.json` — chart.js + vue-chartjs dependencies
- `README.md` — composer run dev quick start section

---

## [2026-04-28] - DataNarasi AI Integration & Fixes

### Added
- Integrated **NVIDIA NIM** AI provider (`meta/llama-3.1-8b-instruct`).
- Integrated **MiniMax** AI provider (`MiniMax-M2.5`) with automatic `<think>` tag stripping.
- Registered both new providers into the `AIProviderManager` fallback chain.
- Added `MINIMAX_API_KEY` to environment variables (`.env`, `.env.example`, `python-service/.env`).
- Seeded NVIDIA and MiniMax providers into the `ai_providers` database table via `AIProviderSeeder`.

### Changed
- Refactored `AIProviderManager` to dynamically load the fallback order (`AI_PROVIDER_ORDER`) from the `.env` file instead of using a hardcoded list.
- Updated `PROMPT.md` and `.cursorrules` to reflect the new 6-provider fallback chain: Gemini → Kimi → GLM → NVIDIA NIM → MiniMax → Claude.
- Improved the project's `README.md` with a comprehensive guide, badges, architecture diagrams, and AI provider tables.
- Updated `.gitignore` to exclude `python-service/.env`, `__pycache__` directories, and `pip_out.log`.

### Fixed
- Fixed a timeout issue between Laravel and the Python FastAPI service. Moved the heavy data processing pipeline (cleansing, analysis, chart generation, and AI narrative generation) into FastAPI `BackgroundTasks`. The `/process` endpoint now immediately returns `202 Accepted` to prevent Laravel's 10-second `cURL` timeout.
- Handled a `500 Internal Server Error` during the Laravel callback by making `ai_provider_id` nullable in the `AIUsageLog::create` method, ensuring missing database providers don't crash the callback handler.
- Resolved `php artisan pail` extension warning (normal on Windows) and verified successful background processing via Laravel logs.## [Unreleased](https://github.com/laravel/laravel/compare/v12.12.2...12.x)

## [v12.12.2](https://github.com/laravel/laravel/compare/v12.12.1...v12.12.2) - 2026-03-14

* [12.x] Add `APP_NAME` fallback in Slack log channel username by [@hamedelasma](https://github.com/hamedelasma) in https://github.com/laravel/laravel/pull/6762

## [v12.12.1](https://github.com/laravel/laravel/compare/v12.12.0...v12.12.1) - 2026-03-10

* [12.x] Makes imports consistent by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/laravel/pull/6760

## [v12.12.0](https://github.com/laravel/laravel/compare/v12.11.2...v12.12.0) - 2026-03-09

* Update phpunit version to ^11.5.50 to address CVE by [@PerryvanderMeer](https://github.com/PerryvanderMeer) in https://github.com/laravel/laravel/pull/6746
* [12.x] Add `APP_NAME` fallback in mail config by [@apoorvdarshan](https://github.com/apoorvdarshan) in https://github.com/laravel/laravel/pull/6755
* [12.x] Neutralize DB_URL in default phpunit.xml by [@Husseinadq](https://github.com/Husseinadq) in https://github.com/laravel/laravel/pull/6761

## [v12.11.2](https://github.com/laravel/laravel/compare/v12.11.1...v12.11.2) - 2026-01-19

* [12.x] Update composer dev script to ensure no timeout by [@jackbayliss](https://github.com/jackbayliss) in https://github.com/laravel/laravel/pull/6735
* [12.x] Update jobs/cache migrations by [@jackbayliss](https://github.com/jackbayliss) in https://github.com/laravel/laravel/pull/6736
* [12.x] Remove failed jobs indexes by [@jackbayliss](https://github.com/jackbayliss) in https://github.com/laravel/laravel/pull/6739
* [12.x] Add `APP_URL` fallback in filesystems config by [@KentarouTakeda](https://github.com/KentarouTakeda) in https://github.com/laravel/laravel/pull/6742
* chore: Update outdated GitHub Actions version by [@pgoslatara](https://github.com/pgoslatara) in https://github.com/laravel/laravel/pull/6743

## [v12.11.1](https://github.com/laravel/laravel/compare/v12.11.0...v12.11.1) - 2025-12-23

* Use environment variable for `DB_SSLMODE` - Postgres by [@robsontenorio](https://github.com/robsontenorio) in https://github.com/laravel/laravel/pull/6727
* fix: ensure APP_URL does not have trailing slash in filesystem by [@msamgan](https://github.com/msamgan) in https://github.com/laravel/laravel/pull/6728

## [v12.11.0](https://github.com/laravel/laravel/compare/v12.10.1...v12.11.0) - 2025-11-25

* fix: cookies are not available for subdomains by default by [@joostdebruijn](https://github.com/joostdebruijn) in https://github.com/laravel/laravel/pull/6705
* Fix PHP 8.5 PDO Driver Specific Constant Deprecation by [@RyanSchaefer](https://github.com/RyanSchaefer) in https://github.com/laravel/laravel/pull/6710
* Ignore Laravel compiled views for Vite  by [@QistiAmal1212](https://github.com/QistiAmal1212) in https://github.com/laravel/laravel/pull/6714

## [v12.10.1](https://github.com/laravel/laravel/compare/v12.10.0...v12.10.1) - 2025-11-06

* Update schema URL in package.json by [@robinmiau](https://github.com/robinmiau) in https://github.com/laravel/laravel/pull/6701

## [v12.10.0](https://github.com/laravel/laravel/compare/v12.9.1...v12.10.0) - 2025-11-04

* Add background driver by [@barryvdh](https://github.com/barryvdh) in https://github.com/laravel/laravel/pull/6699

## [v12.9.1](https://github.com/laravel/laravel/compare/v12.9.0...v12.9.1) - 2025-10-23

* [12.x] Replace Bootcamp with Laravel Learn by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6692
* [12.x] Comment out CLI workers for fresh applications by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/laravel/pull/6693

## [v12.9.0](https://github.com/laravel/laravel/compare/v12.8.0...v12.9.0) - 2025-10-21

**Full Changelog**: https://github.com/laravel/laravel/compare/v12.8.0...v12.9.0

## [v12.8.0](https://github.com/laravel/laravel/compare/v12.7.1...v12.8.0) - 2025-10-20

* [12.x] Makes test suite using broadcast's `null` driver by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/laravel/pull/6691

## [v12.7.1](https://github.com/laravel/laravel/compare/v12.7.0...v12.7.1) - 2025-10-15

* Added `failover` driver to the `queue` config comment.  by [@sajjadhossainshohag](https://github.com/sajjadhossainshohag) in https://github.com/laravel/laravel/pull/6688

## [v12.7.0](https://github.com/laravel/laravel/compare/v12.6.0...v12.7.0) - 2025-10-14

**Full Changelog**: https://github.com/laravel/laravel/compare/v12.6.0...v12.7.0

## [v12.6.0](https://github.com/laravel/laravel/compare/v12.5.0...v12.6.0) - 2025-10-02

* Fix setup script by [@goldmont](https://github.com/goldmont) in https://github.com/laravel/laravel/pull/6682

## [v12.5.0](https://github.com/laravel/laravel/compare/v12.4.0...v12.5.0) - 2025-09-30

* [12.x] Fix type casting for environment variables in config files by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6670
* Fix CVEs affecting vite by [@faissaloux](https://github.com/faissaloux) in https://github.com/laravel/laravel/pull/6672
* Update .editorconfig to target compose.yaml by [@fredikaputra](https://github.com/fredikaputra) in https://github.com/laravel/laravel/pull/6679
* Add pre-package-uninstall script to composer.json by [@cosmastech](https://github.com/cosmastech) in https://github.com/laravel/laravel/pull/6681

## [v12.4.0](https://github.com/laravel/laravel/compare/v12.3.1...v12.4.0) - 2025-08-29

* [12.x] Add default Redis retry configuration by [@mateusjatenee](https://github.com/mateusjatenee) in https://github.com/laravel/laravel/pull/6666

## [v12.3.1](https://github.com/laravel/laravel/compare/v12.3.0...v12.3.1) - 2025-08-21

* [12.x] Bump Pint version by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6653
* [12.x] Making sure all related processed are closed when terminating the currently command by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6654
* [12.x] Use application name from configuration by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6655
* Bring back postAutoloadDump script by [@jasonvarga](https://github.com/jasonvarga) in https://github.com/laravel/laravel/pull/6662

## [v12.3.0](https://github.com/laravel/laravel/compare/v12.2.0...v12.3.0) - 2025-08-03

* Fix Critical Security Vulnerability in form-data Dependency by [@izzygld](https://github.com/izzygld) in https://github.com/laravel/laravel/pull/6645
* Revert "fix" by [@RobertBoes](https://github.com/RobertBoes) in https://github.com/laravel/laravel/pull/6646
* Change composer post-autoload-dump script to Artisan command by [@lmjhs](https://github.com/lmjhs) in https://github.com/laravel/laravel/pull/6647

## [v12.2.0](https://github.com/laravel/laravel/compare/v12.1.0...v12.2.0) - 2025-07-11

* Add Vite 7 support by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/laravel/pull/6639

## [v12.1.0](https://github.com/laravel/laravel/compare/v12.0.11...v12.1.0) - 2025-07-03

* [12.x] Disable nightwatch in testing by [@laserhybiz](https://github.com/laserhybiz) in https://github.com/laravel/laravel/pull/6632
* [12.x] Reorder environment variables in phpunit.xml for logical grouping by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6634
* Change to hyphenate prefixes and cookie names by [@u01jmg3](https://github.com/u01jmg3) in https://github.com/laravel/laravel/pull/6636
* [12.x] Fix type casting for environment variables in config files by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6637

## [v12.0.11](https://github.com/laravel/laravel/compare/v12.0.10...v12.0.11) - 2025-06-10

**Full Changelog**: https://github.com/laravel/laravel/compare/v12.0.10...v12.0.11

## [v12.0.10](https://github.com/laravel/laravel/compare/v12.0.9...v12.0.10) - 2025-06-09

* fix alphabetical order by [@Khuthaily](https://github.com/Khuthaily) in https://github.com/laravel/laravel/pull/6627
* [12.x] Reduce redundancy and keeps the .gitignore file cleaner by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6629
* [12.x] Fix: Add void return type to satisfy Rector analysis by [@Aluisio-Pires](https://github.com/Aluisio-Pires) in https://github.com/laravel/laravel/pull/6628

## [v12.0.9](https://github.com/laravel/laravel/compare/v12.0.8...v12.0.9) - 2025-05-26

* [12.x] Remove apc by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6611
* [12.x] Add JSON Schema to package.json by [@martinbean](https://github.com/martinbean) in https://github.com/laravel/laravel/pull/6613
* Minor language update by [@woganmay](https://github.com/woganmay) in https://github.com/laravel/laravel/pull/6615
* Enhance .gitignore to exclude common OS and log files by [@mohammadRezaei1380](https://github.com/mohammadRezaei1380) in https://github.com/laravel/laravel/pull/6619

## [v12.0.8](https://github.com/laravel/laravel/compare/v12.0.7...v12.0.8) - 2025-05-12

* [12.x] Clean up URL formatting in README by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6601

## [v12.0.7](https://github.com/laravel/laravel/compare/v12.0.6...v12.0.7) - 2025-04-15

* Add `composer run test` command by [@crynobone](https://github.com/crynobone) in https://github.com/laravel/laravel/pull/6598
* Partner Directory Changes in ReadME by [@joshcirre](https://github.com/joshcirre) in https://github.com/laravel/laravel/pull/6599

## [v12.0.6](https://github.com/laravel/laravel/compare/v12.0.5...v12.0.6) - 2025-04-08

**Full Changelog**: https://github.com/laravel/laravel/compare/v12.0.5...v12.0.6

## [v12.0.5](https://github.com/laravel/laravel/compare/v12.0.4...v12.0.5) - 2025-04-02

* [12.x] Update `config/mail.php` to match the latest core configuration by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6594

## [v12.0.4](https://github.com/laravel/laravel/compare/v12.0.3...v12.0.4) - 2025-03-31

* Bump vite from 6.0.11 to 6.2.3 - Vulnerability patch by [@abdel-aouby](https://github.com/abdel-aouby) in https://github.com/laravel/laravel/pull/6586
* Bump vite from 6.2.3 to 6.2.4 by [@thinkverse](https://github.com/thinkverse) in https://github.com/laravel/laravel/pull/6590

## [v12.0.3](https://github.com/laravel/laravel/compare/v12.0.2...v12.0.3) - 2025-03-17

* Remove reverted change from CHANGELOG.md by [@AJenbo](https://github.com/AJenbo) in https://github.com/laravel/laravel/pull/6565
* Improves clarity in app.css file by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6569
* [12.x] Refactor: Structural improvement for clarity by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6574
* Bump axios from 1.7.9 to 1.8.2 - Vulnerability patch by [@abdel-aouby](https://github.com/abdel-aouby) in https://github.com/laravel/laravel/pull/6572
* [12.x] Remove Unnecessarily [@source](https://github.com/source) by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6584

## [v12.0.2](https://github.com/laravel/laravel/compare/v12.0.1...v12.0.2) - 2025-03-04

* Make the github test action run out of the box independent of the choice of testing framework by [@ndeblauw](https://github.com/ndeblauw) in https://github.com/laravel/laravel/pull/6555

## [v12.0.1](https://github.com/laravel/laravel/compare/v12.0.0...v12.0.1) - 2025-02-24

* [12.x] prefer stable stability by [@pataar](https://github.com/pataar) in https://github.com/laravel/laravel/pull/6548

## [v12.0.0 (2025-??-??)](https://github.com/laravel/laravel/compare/v11.0.2...v12.0.0)

Laravel 12 includes a variety of changes to the application skeleton. Please consult the diff to see what's new.
