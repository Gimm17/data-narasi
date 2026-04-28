# Analisis Error Upload: `Column not found: 1054 Unknown column 'title'`

## 1. Penyebab Utama (Root Cause)
Terjadi **ketidaksesuaian (mismatch) yang signifikan** antara atribut yang dikirimkan oleh Controller/Model dengan struktur tabel `reports` yang sebenarnya ada di dalam database MySQL.

Saat Anda memanggil `$report = Report::create([...])` di `UploadController.php`, Eloquent mencoba memasukkan sekumpulan data berdasarkan logika aplikasi terbaru. Namun, file migrasi database (`database/migrations/2026_03_23_103807_create_reports_table.php`) rupanya masih menggunakan skema tabel versi lama/sederhana yang belum mengakomodasi kebutuhan data tersebut.

**Detail Ketidakcocokan Kolom saat Upload:**
| Data dari UploadController | Kolom yang Diharapkan Model | Kolom Aktual di DataBase (*Migration*) | Status Kesesuaian |
| :--- | :--- | :--- | :--- |
| `$request->getTitle()` | `title` | ❌ **TIDAK ADA** | **Ini penyebab utama Error SQL 1054** |
| `$file->getClientOriginalName()` | `original_filename` | `original_filename` | ✅ Sesuai |
| `$filePath` | `original_path` | `file_path` | ❌ Beda Nama |
| Enum *AnalysisType* | `dataset_type` | `analysis_type` | ❌ Beda Nama |
| `0` (Default Baris) | `total_rows` | ❌ **TIDAK ADA** | ❌ Akan memicu error berikutnya |
| `0` (Default Baris) | `clean_rows` | ❌ **TIDAK ADA** | ❌ Akan memicu error berikutnya |

---

## 2. Kenapa Bisa Terjadi?
Sebagian besar kode `App\Models\Report.php` dan `UploadController.php` telah dikembangkan dengan fitur yang sangat canggih (menyimpan statistik AI, log pembersihan kolom, dll). Model `Report` mendaftarkan banyak field di properti `$fillable`:
```php
protected $fillable = [
    'user_id', 'title', 'original_filename', 'original_path', 'clean_path', 
    'status', 'dataset_type', 'total_rows', 'clean_rows', 'summary_stats', 
    'cleaning_log', 'chart_paths', 'ai_narrative', 'ai_provider_used', 
    'processing_time_ms', 'error_message'
];
```
Namun, fisik tabel `reports` di MySQL (berdasarkan migrasinya) hanya memiliki:
```php
$table->id();
$table->foreignId('user_id');
$table->string('original_filename');
$table->string('file_path');
$table->enum('status', [...]);
$table->enum('analysis_type', [...]);
$table->text('error_message')->nullable();
$table->timestamps();
```

---

## 3. Langkah Perbaikan yang Direkomendasikan
Karena instruksi Anda adalah **"JANGAN LANGSUNG DIPERBAIKI DULU"**, saya hanya membuatkan rincian langkah kerja. Jika Anda memberi izin, kita bisa mengeksekusi langkah berikut:

### Opsi A: Migrasi Ulang Tabel Reports (Sangat Direkomendasikan)
Karena aplikasi ini masih dalam tahap *development* awal, cara paling bersih adalah mengubah file `2026_03_23_103807_create_reports_table.php` agar sama persis dengan properti `$fillable` di model, kemudian melakukan **Migrate Fresh**.
1. Buka file migrasi `create_reports_table.php`
2. Tambahkan semua kolom yang kurang:
   ```php
   $table->string('title')->nullable();
   $table->string('original_path'); // Mengganti file_path
   $table->string('clean_path')->nullable();
    // Mengganti nama analysis_type menjadi dataset_type
   $table->string('dataset_type')->default('umum'); 
   $table->integer('total_rows')->default(0);
   $table->integer('clean_rows')->default(0);
   $table->json('summary_stats')->nullable();
   $table->json('cleaning_log')->nullable();
   $table->json('chart_paths')->nullable();
   $table->text('ai_narrative')->nullable();
   $table->string('ai_provider_used')->nullable();
   $table->integer('processing_time_ms')->nullable();
   ```
3. Melakukan eksekusi perintah terminal: `php artisan migrate:fresh --seed`

### Opsi B: Sesuaikan Controller dan Model ke Skema Lama (Tidak Disarankan)
Jika tidak ingin mengubah *database*, maka kita terpaksa harus menghapus sebagian besar fitur di `UploadController` dan Model `Report` agar tidak mengirimkan `title`, `total_rows`, dll. Ini akan men-*downgrade* kapabilitas sistem AI analisis yang telah Anda rancang sebelumnya.

---
*Silakan berikan konfirmasi apakah Anda setuju untuk mengeksekusi **Opsi A (Migrasi Database Spesifikasi Baru)**.*
