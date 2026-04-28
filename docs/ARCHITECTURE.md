# Arsitektur DataNarasi

## Overview

DataNarasi terdiri dari dua service yang berjalan terpisah dan berkomunikasi via HTTP:

1. **Laravel App** — mengelola user, autentikasi, queue job, dan penyajian UI
2. **Python FastAPI Service** — memproses data (cleansing, analisis, chart, AI narrative)

## Alur Request Lengkap

```
User upload CSV
    ↓
Laravel UploadController
    → validasi file (mime, size, extension)
    → simpan ke storage/app/uploads/
    → buat record Report (status: pending)
    → dispatch ProcessDataJob ke Redis Queue
    ↓
ProcessDataJob (berjalan async)
    → update status: processing
    → kirim POST ke Python service: /process
    → payload: { report_id, file_path, analysis_type, tone }
    ↓
Python FastAPI /process endpoint
    → cleaner.py: bersihkan data
    → analyzer.py: hitung statistik
    → chart_generator.py: buat chart PNG
    → prompt_builder.py: rakit prompt
    → ai_provider.py: panggil AI (dengan fallback)
    → callback ke Laravel: POST /api/v1/reports/{id}/callback
    ↓
Laravel menerima callback
    → simpan narrative, chart_paths, summary_stats ke DB
    → update status: done
    → broadcast ke frontend via Pusher/SSE (opsional)
    ↓
User melihat hasil di /reports/{id}
```

## Komponen Utama

### Laravel Side

| Komponen | Lokasi | Fungsi |
|---|---|---|
| UploadController | app/Http/Controllers/ | Terima upload, validasi, dispatch job |
| ReportController | app/Http/Controllers/ | CRUD report, serve hasil |
| ProcessDataJob | app/Jobs/ | Koordinasi antara Laravel dan Python |
| PythonServiceClient | app/Services/ | HTTP client ke Python service |
| Report (Model) | app/Models/ | Eloquent model utama |
| AIProvider (Model) | app/Models/ | Konfigurasi provider AI |
| AIUsageLog (Model) | app/Models/ | Audit trail semua AI calls |

### Python Side

| Modul | Fungsi |
|---|---|
| main.py | FastAPI router, endpoint /process dan /health |
| cleaner.py | Pandas pipeline: duplikat, null, tipe data, encoding |
| analyzer.py | Statistik: sum, avg, tren, top-N, outlier, korelasi |
| chart_generator.py | Matplotlib: line chart, bar chart, pie chart → PNG |
| prompt_builder.py | Rakit 4-layer prompt per tipe analisis dan tone |
| ai_provider.py | Chain pattern: coba provider 1→2→3→4 |
| providers/base.py | Abstract class semua AI provider |
| providers/gemini.py | Google Gemini implementation |
| providers/kimi.py | Moonshot Kimi implementation |
| providers/glm.py | Zhipu GLM implementation |
| providers/claude.py | Anthropic Claude implementation |

## Database Schema

Lihat ERD di docs/ERD.md atau langsung cek migrations di database/migrations/.

Tabel utama (urutan dependency):
1. users
2. ai_providers
3. reports (FK: user_id)
4. report_columns (FK: report_id)
5. ai_usage_logs (FK: report_id, provider_id)
6. report_outputs (FK: report_id)

## Multi-AI Fallback Logic

```python
providers = [gemini, kimi, glm, claude]  # difilter dari DB berdasarkan is_enabled

for provider in providers:
    try:
        result = provider.generate(prompt, system, max_tokens)
        if validate_narrative(result):
            log_success(provider, report_id)
            return result
        else:
            log_validation_fail(provider, report_id)
            continue  # coba provider berikutnya
    except RateLimitError:
        log_rate_limit(provider, report_id)
        continue
    except Exception as e:
        log_error(provider, report_id, e)
        continue

# Semua provider gagal → gunakan template statis
return generate_template_narrative(stats)
```

## Deployment (Railway)

Dua service di Railway dalam satu project:

- **web** (Laravel): build dari root, start dengan php artisan serve
- **python** (FastAPI): build dari /python-service, start dengan uvicorn

Internal communication menggunakan Railway private network URL.
External access hanya melalui Laravel app (Python service tidak expose ke publik).
