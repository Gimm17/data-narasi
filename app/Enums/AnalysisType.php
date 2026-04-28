<?php

namespace App\Enums;

/**
 * Tipe analisis yang tersedia untuk dataset
 */
enum AnalysisType: string
{
    case Penjualan = 'penjualan';
    case Keuangan = 'keuangan';
    case Operasional = 'operasional';
    case Marketing = 'marketing';
    case Inventori = 'inventori';
    case SDM = 'sdm';
    case Akademik = 'akademik';
    case Kesehatan = 'kesehatan';
    case Logistik = 'logistik';
    case Survey = 'survey';
    case Umum = 'umum';

    /**
     * Label untuk tampilan UI
     */
    public function label(): string
    {
        return match ($this) {
            self::Penjualan => 'Penjualan',
            self::Keuangan => 'Keuangan',
            self::Operasional => 'Operasional',
            self::Marketing => 'Marketing',
            self::Inventori => 'Inventori',
            self::SDM => 'SDM / HR',
            self::Akademik => 'Akademik',
            self::Kesehatan => 'Kesehatan',
            self::Logistik => 'Logistik',
            self::Survey => 'Survey / Polling',
            self::Umum => 'Umum',
        };
    }

    /**
     * Fokus analisis untuk prompt AI
     * Memberikan konteks spesifik kepada AI tentang aspek apa yang harus dianalisis
     */
    public function emphasis(): string
    {
        return match ($this) {
            self::Penjualan => 'Fokus pada tren penjualan, produk terlaris, pertumbuhan revenue, dan pola pembelian pelanggan.',
            self::Keuangan => 'Fokus pada arus kas, profitabilitas, rasio keuangan, dan kesehatan finansial.',
            self::Operasional => 'Fokus pada efisiensi operasional, produktivitas, dan penggunaan resources.',
            self::Marketing => 'Fokus pada performa kampanye, conversion rate, dan engagement pelanggan.',
            self::Inventori => 'Fokus pada turnover inventory, stock levels, dan permintaan produk.',
            self::SDM => 'Fokus pada kinerja karyawan, turnover rate, kepuasan kerja, absensi, dan demografi SDM.',
            self::Akademik => 'Fokus pada performa akademik, nilai rata-rata, distribusi nilai, kelulusan, dan tren per mata pelajaran.',
            self::Kesehatan => 'Fokus pada data pasien, prevalensi penyakit, tren kesehatan, dan indikator klinis.',
            self::Logistik => 'Fokus pada efisiensi pengiriman, lead time, biaya transportasi, dan ketepatan waktu.',
            self::Survey => 'Fokus pada distribusi jawaban, sentimen responden, korelasi antar variabel, dan insight kualitatif.',
            self::Umum => 'Analisis deskriptif umum dari data dengan insight yang relevan.',
        };
    }

    /**
     * Icon emoji untuk UI
     */
    public function icon(): string
    {
        return match ($this) {
            self::Penjualan => '💰',
            self::Keuangan => '📊',
            self::Operasional => '⚙️',
            self::Marketing => '📣',
            self::Inventori => '📦',
            self::SDM => '👥',
            self::Akademik => '🎓',
            self::Kesehatan => '🏥',
            self::Logistik => '🚚',
            self::Survey => '📋',
            self::Umum => '📈',
        };
    }

    /**
     * Deskripsi singkat untuk help text
     */
    public function description(): string
    {
        return match ($this) {
            self::Penjualan => 'Data transaksi penjualan dan revenue',
            self::Keuangan => 'Data laporan keuangan dan arus kas',
            self::Operasional => 'Data operasional dan produktivitas',
            self::Marketing => 'Data kampanye dan engagement',
            self::Inventori => 'Data stok dan inventory',
            self::SDM => 'Data karyawan, absensi, dan kinerja',
            self::Akademik => 'Data nilai, kelulusan, dan rapor',
            self::Kesehatan => 'Data medis, pasien, dan klinik',
            self::Logistik => 'Data pengiriman dan distribusi',
            self::Survey => 'Data kuesioner dan polling',
            self::Umum => 'Analisis umum tanpa spesialisasi',
        };
    }
}
