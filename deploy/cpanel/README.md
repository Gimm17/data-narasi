# Deploy DataNarasi ke Shared Hosting cPanel

Panduan ini mengikuti pola yang sudah terbukti pada `E:\BPS\REVISI-PUBLIKASI\web`
(IndoGlobal cPanel, PHP 8.4, tanpa layanan Python).

Aplikasi kini **tidak membutuhkan Python, Node di server, Redis, atau Supervisor**.
Seluruh pipeline (pembersihan data → analisis statistik → chart PNG → narasi AI)
berjalan di dalam PHP.

---

## 1. Prasyarat server

| Kebutuhan | Nilai | Catatan |
|---|---|---|
| PHP CLI + FPM | 8.3 atau 8.4 | cPanel biasanya `/opt/alt/php84/usr/bin/php` |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `gd` (FreeType + PNG), `zip`, `curl`, `openssl`, `fileinfo`, `json` | **GD wajib** — tanpa GD chart tidak bisa dibuat |
| Database | MySQL 8 / MariaDB 10.6+ | |
| Disk | ± 1 GB | vendor ~60 MB, aset build ~2 MB, sisa untuk storage |
| Cron | 1 job per menit | untuk queue worker |

### Versi PHP per domain — WAJIB dicek

Versi default akun bisa **lebih rendah** dari yang dibutuhkan Laravel 13
(`^8.3`) meski PHP CLI di terminal sudah 8.4. Di server contoh, subdomain baru
otomatis memakai `ea-php82` sementara yang lain memakai `alt-php84`.

```bash
# lihat versi tiap vhost
uapi LangPHP php_get_vhost_versions | grep -E 'documentroot|version|domain'

# set ke 8.4
uapi LangPHP php_set_vhost_versions version=alt-php84 vhost=sub.domain.com

# konfirmasi
uapi LangPHP php_get_vhost_versions | grep -A7 'documentroot: /path/anda$'
```

Cara cepat memastikan versi yang benar-benar melayani web:
```bash
cat > public/phpver.php <<'EOF'
<?php echo PHP_VERSION;
EOF
curl -s https://DOMAIN/phpver.php; rm public/phpver.php
```

Cek ekstensi lewat cPanel → **Select PHP Version → Extensions**, atau:
```bash
/opt/alt/php84/usr/bin/php -m | grep -E 'gd|pdo_mysql|mbstring|zip|curl'
```

---

## 2. Struktur direktori

Dua pola didukung. **Pola A lebih aman** dan dipakai bila document root bisa
diubah; **pola B** dipakai bila tidak bisa.

### Pola A — document root diarahkan ke `public/` (disarankan)

```
/home/USER/
├── apps/datanarasi/            ← seluruh kode aplikasi
│   ├── app/ config/ routes/ resources/ vendor/
│   ├── public/                 ← document root domain
│   └── storage/
└── public_html/                ← (atau docroot subdomain)
```

Di cPanel: **Domains → Domain → Document Root** diarahkan ke
`/home/USER/apps/datanarasi/public`. Bisa juga lewat terminal:

```bash
uapi SubDomain changedocroot \
  domain=sub.domain.com \
  rootdomain=domain.com \
  docroot=/home/USER/apps/datanarasi/public
```

### Pola B — document root dibiarkan, aplikasi ditaruh di dalamnya

```
/home/USER/sub.domain.com/      ← document root DAN root aplikasi
├── .htaccess                   ← dari deploy/cpanel/root.htaccess
├── .env  app/  vendor/  storage/
└── public/                     ← dilayani lewat rewrite .htaccess
```

Pola ini **lebih rentan**: `.env`, `vendor/`, dan `storage/` berada di dalam
webroot, jadi yang menahan akses adalah aturan di `root.htaccess`, bukan
penempatan berkas. Berkas itu wajib diunggah ke document root.

---

## 3. Build aset di komputer lokal

Server hanya menerima hasil build — tidak perlu Node di hosting.

```bash
npm ci
npm run build
```

> Kalau `npm run build` gagal dengan *"too many arguments"* di Windows,
> jalankan langsung: `node node_modules/vite/bin/vite.js build`.

Yang perlu diunggah: isi `public/build/`. Jangan commit `public/build`
(already di `.gitignore`), cukup upload lewat File Manager setelah build.

> Font TTF wajib ikut terunggah: `resources/fonts/DejaVuSans.ttf` dan
> `resources/fonts/DejaVuSans-Bold.ttf`. Tanpa keduanya chart gagal dibuat
> karena `imagettftext()` tidak bisa membaca font.

---

## 4. Unggah & pasang

Paket dibuat dengan `deploy/cpanel/build-package.bat` → `data-narasi-deploy.tar.gz`.

1. **Upload** arsip ke document root, lalu **Extract**:
   ```bash
   cd ~/apps/datanarasi        # atau docroot subdomain untuk pola B
   tar -xzf ../data-narasi-deploy.tar.gz
   ```
   Arsip **sudah berisi** folder `storage/` kosong beserta berkas `.gitignore`
   penandanya. Jangan pernah menghapusnya — lihat catatan di bagian 9.
2. `vendor/` **tidak** ikut di arsip (jauh lebih cepat dari mengunggah ~25 ribu
   berkas kecil). Jalankan Composer di server:
   ```bash
   cd ~/apps/datanarasi
   composer install --no-dev --optimize-autoloader --no-interaction
   ```
3. Buat `.env` dari `.env.cpanel.example` (lihat bagian 5).
4. Untuk **pola B**, unggah juga aturan rewrite ke document root:
   ```bash
   cp deploy/cpanel/root.htaccess .htaccess
   sed -i 's#/home/USER/logs#/home/USERNAME/logs#' .htaccess
   ```
   Untuk **pola A** berkas itu tidak diperlukan.
5. Jalankan setup:
   ```bash
   cd ~/apps/datanarasi
   /opt/alt/php84/usr/bin/php artisan key:generate
   /opt/alt/php84/usr/bin/php artisan migrate --force
   /opt/alt/php84/usr/bin/php artisan db:seed --force
   /opt/alt/php84/usr/bin/php artisan storage:link
   /opt/alt/php84/usr/bin/php artisan optimize
   ```
6. Izin direktori — **jangan 777**:
   ```bash
   chmod -R 775 storage bootstrap/cache
   chmod 600 .env
   ```

---

## 4b. Verifikasi cepat dengan `datanarasi:doctor`

Setelah `.env`, migrate, dan seed selesai, jalankan:

```bash
/opt/alt/php84/usr/bin/php artisan datanarasi:doctor
```

Perintah ini **hanya membaca**, tidak mengubah apa pun, dan memeriksa:
versi PHP, ekstensi (GD+FreeType wajib), izin tulis `storage`/`bootstrap/cache`,
keberadaan font TTF, aset `public/build`, koneksi database, kelengkapan
migration, `retry_after` vs timeout job, serta provider AI yang siap pakai.

Semua baris harus **OK** sebelum aplikasi dipakai. Kalau ada yang GAGAL,
detailnya sudah menyebutkan langkah perbaikannya.

---

## 5. Konfigurasi `.env`

Salin `deploy/cpanel/.env.cpanel.example` → `.env`, lalu isi:

- `APP_URL` — domain final dengan `https://`
- `DB_*` — kredensial dari cPanel → **MySQL Databases**
- `LIMITROUTER_API_KEY` (default utama) dan/atau key provider lain
- `ADMIN_EMAIL` + `ADMIN_PASSWORD` — akun admin awal, **wajib diganti**
- `QUEUE_CONNECTION=database`, `CACHE_STORE=file`, `SESSION_DRIVER=file`

> Jangan pernah meng-commit `.env`. File itu berisi semua API key dan
> password admin. Sudah termasuk `.gitignore`.

### Mengganti model AI dari panel admin

Provider **LimitRouter** menjadi default. Daftar modelnya tidak perlu
ditulis manual — buka **Admin → AI Providers → ikon gear (Edit provider)**
lalu klik **"Ambil dari provider"**. Aplikasi memanggil
`GET https://limitrouter.com/v1/models` memakai API key yang tersimpan
dan menampilkan daftar model terbaru (saat ini 42 model). Pilih salah satu,
klik **Simpan** — model baru langsung dipakai pada report berikutnya.

Tombol yang sama juga berfungsi untuk TokenRouter, OpenRouter, NVIDIA,
MiniMax, GLM, Kimi, dan Gemini.

> Catatan: `deepseek-v4.1-flash` adalah model *reasoning*. Kode memanggilnya
> dengan `reasoning_effort: none` agar token berpikir tidak menghabiskan
> jatah output (tanpa ini, 243 dari 282 token habis untuk reasoning dan
> narasi terpotong).

Setelah mengubah `.env` **selalu** jalankan:
```bash
php artisan optimize:clear && php artisan optimize
```

> API key dibaca lewat `config/ai.php`, jadi tetap terbaca setelah
> `config:cache`. Jangan memanggil `env()` dari dalam service — di server
> yang sudah di-cache hasilnya `null`.

---

## 6. Queue worker (wajib)

Job `ProcessDataJob` memproses upload secara asinkron. Tanpa worker,
report akan tertahan di status *Pending*.

Tambahkan cron lewat cPanel → **Cron Jobs**, interval **Once Per Minute**:

```cron
* * * * * /bin/bash /home/USER/apps/datanarasi/deploy/cpanel/queue-worker.sh >> /dev/null 2>&1
```

`/bin/bash` ditulis eksplisit dengan sengaja. Arsip ZIP **tidak menyimpan
bit executable**, jadi memanggil skrip dengan `./queue-worker.sh` langsung
akan gagal dengan *Permission denied* sampai kamu menjalankan
`chmod +x queue-worker.sh`. Menyebut `/bin/bash` menghindari ketergantungan
pada bit itu sama sekali.

Skrip itu memakai `flock -n` sehingga:
- hanya satu worker aktif pada satu waktu (job tidak dobel),
- `--max-time=58` membuat worker berhenti setelah tugas yang sedang jalan selesai — Laravel memeriksa batas ini **antar** job, bukan di tengah job. Job yang sedang berjalan tetap diselesaikan sampai `--timeout=230`,
- selama job panjang berjalan, cron berikutnya gagal mengambil lock dan langsung keluar — itu perilaku yang diinginkan, bukan error.

Urutan batas waktu yang harus dijaga:

```
timeout job (230)  <  retry_after queue (300)
```

`$timeout` di kelas job **mengalahkan** `--timeout` milik worker, jadi angka
yang mengikat adalah milik job. `retry_after` **wajib** lebih besar dari
timeout job; bila terbalik, job yang masih berjalan akan diambil ulang dan
diproses dua kali — panggilan AI dan biayanya ikut berlipat.

Bila PHP berada di lokasi lain, set `CPANEL_PHP_BIN`:
```cron
* * * * * CPANEL_PHP_BIN=/usr/local/bin/php /bin/bash /home/USER/apps/datanarasi/deploy/cpanel/queue-worker.sh >> /dev/null 2>&1
```

---

## 7. Batas PHP — WAJIB lewat `.htaccess`, bukan `.user.ini`

Nilai yang dibutuhkan:

```ini
upload_max_filesize = 12M
post_max_size = 14M
memory_limit = 512M
max_execution_time = 300
```

**Kenapa `.htaccess`:** sebagian cPanel memakai **LiteSpeed** (`php_sapi_name()`
= `litespeed`), dan pada LSAPI berkas `.user.ini` **tidak dibaca sama sekali**.
Terbukti di produksi: `.user.ini` sudah berisi 12M/512M tetapi
`ini_get('upload_max_filesize')` tetap `2M` dan `memory_limit` tetap `128M`.
Setelah blok yang sama dipindah ke `.htaccess` sebagai `php_value`, keduanya
langsung berubah.

Blok ini **sudah ada** di `deploy/cpanel/root.htaccess` (pola B). Untuk pola A,
tambahkan ke `public/.htaccess`:

```apache
<IfModule lsapi_module>
    php_value upload_max_filesize 12M
    php_value post_max_size 14M
    php_value memory_limit 512M
    php_value max_execution_time 300
    php_value max_input_time 300
</IfModule>
```

**Urutan angka yang benar:** `MAX_UPLOAD_SIZE_MB` (.env, 10) < `upload_max_filesize`
(12) < `post_max_size` (14). Kalau terbalik, unggahan ditolak PHP lebih dulu
dan pengguna **tidak melihat pesan apa pun** — hanya halaman yang tidak berubah.

Cara memverifikasi nilainya benar-benar berlaku untuk web (bukan hanya CLI):

```bash
cat > public/cek.php <<'EOF'
<?php echo ini_get('upload_max_filesize'), ' / ', ini_get('memory_limit');
EOF
curl -s https://DOMAIN/cek.php     # harapan: 12M / 512M
rm public/cek.php
```

`memory_limit` 512 MB cukup: dataset uji 60.000 baris × 5 kolom memakai
peak ~7 MB; 198×15 memakai ~52 MB. `max_execution_time` hanya berpengaruh pada
request web — proses berat berjalan di queue worker (`--timeout=230`).

---

## 8. Verifikasi setelah deploy

```bash
# 1. Aplikasi hidup
curl -I https://DOMAIN/            # harapan: HTTP 200

# 2. Halaman upload tampil
curl -s https://DOMAIN/upload | head -c 200

# 3. Queue worker jalan (setelah upload satu file)
/opt/alt/php84/usr/bin/php artisan queue:monitor database:10

# 4. Storage link benar
ls -l public/storage     # harus menunjuk ke ../storage/app/public

# 5. Cek provider AI aktif
/opt/alt/php84/usr/bin/php artisan tinker --execute="
  foreach (App\Models\AIProvider::orderBy('priority')->get() as \$p)
    echo \$p->slug, ' ready=', \$p->isReady() ? 'ya' : 'tidak', PHP_EOL;
"
```

Uji end-to-end: buka `/upload`, unggah CSV kecil, tunggu halaman
*Processing* berpindah ke *Selesai*, lalu pastikan chart tampil dan
narasi terisi. Bukti yang sudah diverifikasi di produksi (60.000 baris ×
5 kolom, 2,24 MB): status `done`, 3 chart PNG 1200×620 termuat,
narasi 673 kata, 19,5 detik, biaya $0,0033.

---

## 9. Troubleshooting

| Gejala | Penyebab umum | Perbaikan |
|---|---|---|
| Halaman putih / 500 | `.env` salah atau `APP_KEY` kosong | cek `storage/logs/laravel.log`, jalankan `key:generate` |
| 500 di **semua** halaman + log *"Please provide a valid cache path"* | folder `storage/framework/views` tidak ada | buat ulang: `mkdir -p storage/framework/{sessions,views,cache/data} storage/logs storage/app/{private,public}` lalu `chmod -R 775 storage`. Laravel hanya membuat sendiri `cache` dan `logs` — `sessions`/`views` **tidak**. Inilah yang terjadi bila arsip deploy dibuat tanpa folder `storage/` |
| 404 semua halaman | document root belum benar | pola A: arahkan ke `.../public`. Pola B: pastikan `root.htaccess` ada di document root |
| Chart 403 / gambar kosong | aturan penolak `storage/` menang atas rewrite `/storage/charts/` | pastikan aturan chart ada **sebelum** larangan `storage/` di `root.htaccess` |
| Report tertahan *Pending* | cron worker belum jalan | cek cron, jalankan worker manual sekali |
| Upload 3–10 MB tidak jalan tanpa pesan | batas PHP, atau `.user.ini` diabaikan LiteSpeed | pindahkan `php_value` ke `.htaccess` (bagian 7) |
| Chart tidak muncul | GD tanpa FreeType, atau font hilang | pastikan `extension=gd` dan `resources/fonts/*.ttf` ada |
| "Semua provider AI gagal" | API key kosong / kuota habis | cek `.env` + Admin → AI Providers → **Cek Key** |
| 419 Page Expired di klien HTTP | token/sesi tidak dibawa antar request — biasanya masalah alat uji, bukan aplikasi | uji dengan browser nyata; cek cookie `XSRF-TOKEN` ikut terkirim |
| Perubahan `.env` tidak terbaca | config masih ter-cache | `php artisan optimize:clear && php artisan optimize` |

> **Jangan** menghapus direktori `storage/`. Ia perlu ada sebelum aplikasi
> dijalankan; isinya boleh kosong selain berkas `.gitignore`.

---

## 10. Update aplikasi

```bash
cd ~/apps/datanarasi
cp .env .env.backup                   # simpan konfigurasi
# unggah kode baru (tanpa .env, tanpa storage/)
/opt/alt/php84/usr/bin/php artisan down
/opt/alt/php84/usr/bin/php artisan migrate --force
/opt/alt/php84/usr/bin/php artisan optimize
/opt/alt/php84/usr/bin/php artisan queue:restart
/opt/alt/php84/usr/bin/php artisan up
```

Jangan pernah menjalankan `migrate:fresh` di produksi — itu menghapus
seluruh report pengguna.

---

## 11. Yang sengaja TIDAK dipakai lagi

| Komponen lama | Pengganti di PHP |
|---|---|
| `python-service/` (FastAPI, pandas, matplotlib) | `app/Services/Analysis/*` |
| `POST /process` + callback HMAC | `AnalysisPipeline::process()` langsung di `ProcessDataJob` |
| `PythonServiceClient` (dihapus) | — |
| `ReportCallbackController` + `POST /api/v1/reports/{id}/callback` (dihapus) | — |
| Redis (`QUEUE_CONNECTION=redis`) | `database` |
| Supervisor | cron + `flock` |
| `/check-api-key` Python | `ApiKeyChecker` |

Folder `python-service/` masih tersimpan sebagai referensi historis dan
tidak dipanggil kode mana pun. Boleh dihapus kapan saja setelah yakin.
