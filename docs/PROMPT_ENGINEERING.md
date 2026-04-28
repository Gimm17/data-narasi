# Prompt Engineering — DataNarasi

## Filosofi Dasar

Python yang menghitung, AI yang menulis.
Jangan suruh AI menghitung — kasih angka yang sudah jadi, minta AI narasi.

## 4-Layer Prompt Structure

### Layer 1: System Prompt (tetap)
Mendefinisikan peran, bahasa output, dan batasan halusinasi.
Dipasang di parameter `system` — semua provider (kecuali GLM yang tidak support,
system dimasukkan ke awal user message).

### Layer 2: Konteks Dataset (dinamis)
Info meta: nama file, jumlah baris, kolom, tipe analisis yang dipilih user.
Dibuat dinamis oleh prompt_builder.py berdasarkan data upload.

### Layer 3: Hasil Kalkulasi (dinamis)
Output dari analyzer.py dalam format teks terstruktur.
Berisi angka-angka real: total revenue, growth rate, top produk, dll.
AI tidak boleh mengarang angka di luar yang disediakan di sini.

### Layer 4: Instruksi Output (dinamis per tone)
Mengatur panjang, struktur paragraf, dan gaya bahasa sesuai pilihan user.
Tiga opsi tone: formal, santai, teknis.

## Validasi Output

Sebelum narrative disimpan ke DB, wajib lolos semua cek:

| Cek | Rule |
|---|---|
| Panjang minimum | >= 80 kata |
| Tidak ada CJK | Tidak ada karakter Mandarin/Jepang/Korea |
| Ada angka | Minimal 1 angka di-mention dalam teks |
| Tidak ada pembuka generik | Tidak diawali "Berikut", "Tentu", "Baik" |
| Bahasa Indonesia | Tidak ada kalimat panjang dalam bahasa lain |

Jika gagal → trigger fallback ke provider berikutnya.
Jika semua provider gagal validasi → gunakan template statis.

## Penyesuaian per Provider

| Provider | Support System | Bahasa | Catatan Khusus |
|---|---|---|---|
| Gemini | Ya | Bagus | Lebih akurat dengan stats dalam format JSON |
| Kimi | Ya | Sangat bagus | Context window besar, ideal untuk dataset panjang |
| GLM | Tidak | Perlu tegas | Wajib instruksi "HANYA Bahasa Indonesia" di user msg |
| Claude | Ya | Sempurna | Paling konsisten ikuti format instruksi |

## Template Statis (Fallback Terakhir)

Jika SEMUA provider gagal, sistem generate narasi dari template:

```
Berdasarkan analisis data [filename], ditemukan total [total_rows] transaksi
dengan nilai keseluruhan sebesar Rp [total_revenue]. Produk dengan penjualan
tertinggi adalah [top_product_1]. Periode analisis mencakup [date_range].
Catatan: narasi ini dibuat secara otomatis karena layanan AI sedang tidak
tersedia. Silakan coba refresh untuk narasi yang lebih lengkap.
```

Template ini memastikan user selalu mendapat output meski AI down semua.
