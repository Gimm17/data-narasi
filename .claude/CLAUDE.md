# DataNarasi — Claude Code Rules
# File ini dibaca otomatis oleh Claude Code setiap sesi dimulai

## Project Overview
DataNarasi adalah aplikasi analisis data CSV/Excel dengan AI narrative generator
berbahasa Indonesia. Stack: Laravel 12 + Vue 3 + Python FastAPI + Multi-AI fallback.

## Baca PROMPT.md untuk konteks lengkap sebelum mulai coding.

## Perilaku Claude Code di Project Ini

### Selalu lakukan ini:
- Baca PROMPT.md di awal setiap sesi baru
- Tunjukkan full file path sebelum setiap code block
- Tulis komentar kode dalam Bahasa Indonesia
- Ikuti urutan Build Order di PROMPT.md
- Konfirmasi dulu sebelum menghapus atau overwrite file yang sudah ada
- Setelah menulis kode, tunjukkan langkah testing/verifikasi-nya

### Jangan lakukan ini:
- Jangan skip migration — selalu buat migration untuk setiap model baru
- Jangan hardcode API key — selalu gunakan env() helper
- Jangan pakai Options API di Vue — wajib Composition API + script setup
- Jangan campur logika bisnis di Controller — gunakan Service/Job class
- Jangan buat endpoint tanpa validasi Form Request

### Format response:
- Kalau ditanya "buat file X", langsung tulis kodenya tanpa tanya-tanya dulu
- Kalau ada ambiguitas, tanya SATU pertanyaan yang paling penting saja
- Gunakan bahasa Indonesia untuk penjelasan, bahasa Inggris untuk kode

## Tech Stack Quick Reference
- PHP 8.2 / Laravel 12
- Vue 3 (Composition API, script setup)
- Inertia.js (bukan SPA murni, bukan full SSR)
- Tailwind CSS
- MySQL + Redis
- Python 3.11 + FastAPI + Pandas
- Multi-AI: Gemini → Kimi → GLM → Claude (fallback order)
