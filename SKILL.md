# SKILL.md — DataNarasi Development Skills
# Panduan teknis spesifik untuk Claude Code agar output konsisten

## Skill: Multi-AI Provider Pattern

### Cara menambah provider baru
1. Buat file baru di python-service/providers/nama_provider.py
2. Inherit dari BaseAIProvider (python-service/providers/base.py)
3. Override method: generate(prompt, system_prompt, max_tokens) -> str
4. Daftarkan di AI_PROVIDERS list di python-service/ai_provider.py
5. Tambahkan env key di .env.example dan .env

### Error yang harus di-handle per provider
- RateLimitError: HTTP 429 → trigger fallback ke next provider
- TimeoutError: request > timeout_seconds config → trigger fallback
- AuthError: HTTP 401/403 → disable provider, log error, trigger fallback
- ContentFilterError: provider reject content → log, trigger fallback
- Semua error lain: log, trigger fallback

---

## Skill: Laravel Queue Job Pattern

### ProcessDataJob structure
```php
// Selalu implement ShouldQueue, menggunakan Redis
// Timeout: 300 detik (5 menit)
// Tries: 1 (tidak auto-retry — error harus dicatat, user dikabari)
// OnFailure: update report status ke 'failed', kirim notifikasi
```

### Job flow
1. Ambil Report dari DB berdasarkan report_id
2. Update status → 'processing'
3. Call Python service via HTTP (PythonServiceClient)
4. Python service callback ke Laravel saat selesai
5. Update report dengan hasil: narrative, chart_paths, summary_stats
6. Update status → 'done'

---

## Skill: Pandas Cleansing Pipeline

### Urutan operasi wajib (jangan diubah urutannya)
1. Deteksi encoding (chardet)
2. Baca file dengan encoding yang terdeteksi
3. Strip whitespace semua string columns
4. Hapus baris yang SEMUA kolomnya kosong
5. Hapus duplikat exact
6. Deteksi dan konversi tipe data otomatis (angka, tanggal)
7. Handle nilai negatif di kolom numeric (flag sebagai anomali)
8. Isi null: numeric → median, string → 'Tidak diketahui'
9. Catat semua perubahan ke cleaning_log dict

### Return format dari cleaner.py
```python
{
    "clean_df": pd.DataFrame,
    "cleaning_log": {
        "original_rows": int,
        "final_rows": int,
        "duplicates_removed": int,
        "nulls_filled": dict,  # {column: count}
        "type_conversions": list,
        "anomalies_flagged": list
    }
}
```

---

## Skill: Prompt Builder

### Struktur prompt final yang dikirim ke AI
```
[SYSTEM]: Peran + aturan bahasa + anti-halusinasi rules
[USER]:
  ## Konteks dataset
  [meta info]

  ## Hasil kalkulasi
  [stats block dari Python]

  ## Fokus analisis
  [berdasarkan analysis_type]

  ## Instruksi output
  [tone instruction]
```

### Validasi output AI sebelum disimpan
- Minimal 80 kata
- Tidak mengandung karakter CJK (anti-Mandarin untuk GLM)
- Minimal ada 1 angka yang disebutkan
- Tidak diawali dengan "Berikut" / "Tentu" / "Baik"
- Jika gagal validasi → trigger fallback ke provider berikutnya

---

## Skill: Inertia.js + Vue 3 Pages

### Template dasar setiap Page
```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// Props dari Laravel controller
const props = defineProps<{
  // definisikan props di sini
}>()
</script>

<template>
  <Head title="Nama Halaman" />
  <AppLayout>
    <!-- konten -->
  </AppLayout>
</template>
```

### Cara handle Inertia form submission
```vue
import { useForm } from '@inertiajs/vue3'
const form = useForm({ field: '' })
form.post(route('nama.route'), {
  onSuccess: () => { /* redirect otomatis */ },
  onError: (errors) => { /* handle validation errors */ }
})
```

---

## Skill: Railway Deployment

### Dua service di Railway
1. Laravel App (PHP + Nginx): root directory = /
2. Python Service (FastAPI): root directory = /python-service

### Environment variables Railway
- Set PYTHON_SERVICE_URL ke internal Railway URL python service
- Set APP_URL ke domain Railway Laravel app
- Railway otomatis inject DATABASE_URL dan REDIS_URL

### Procfile untuk Python service
```
web: uvicorn main:app --host 0.0.0.0 --port $PORT
```

### railway.json untuk Laravel
```json
{
  "build": { "builder": "nixpacks" },
  "deploy": {
    "startCommand": "php artisan migrate --force && php artisan serve --host 0.0.0.0 --port $PORT"
  }
}
```
