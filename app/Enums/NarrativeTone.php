<?php

namespace App\Enums;

/**
 * Tone narasi yang digunakan untuk menghasilkan narasi dari AI
 */
enum NarrativeTone: string
{
    case Formal = 'formal';
    case Santai = 'santai';
    case Teknis = 'teknis';
    case Eksekutif = 'eksekutif';
    case Storytelling = 'storytelling';
    case Akademis = 'akademis';

    /**
     * Label untuk tampilan UI
     */
    public function label(): string
    {
        return match ($this) {
            self::Formal => 'Formal',
            self::Santai => 'Santai',
            self::Teknis => 'Teknis',
            self::Eksekutif => 'Eksekutif',
            self::Storytelling => 'Storytelling',
            self::Akademis => 'Akademis',
        };
    }

    /**
     * Instruksi tone untuk prompt AI
     * Memberikan arahan spesifik kepada AI tentang gaya bahasa yang digunakan
     */
    public function instruction(): string
    {
        return match ($this) {
            self::Formal => 'Gunakan bahasa formal dan profesional. Hindari slang atau bahasa gaul. Gunakan struktur kalimat yang lengkap dan rapi. Cocok untuk laporan eksekutif atau presentasi bisnis.',
            self::Santai => 'Gunakan bahasa yang santai dan mudah dipahami. Boleh menggunakan analogi sederhana. Hindari istilah teknis yang berlebihan. Cocok untuk pembaca awam atau briefing cepat.',
            self::Teknis => 'Gunakan bahasa teknis dan presisi. Sebutkan metrik spesifik, rumus, atau terminologi domain yang relevan. Asumsikan pembaca memiliki pengetahuan teknis. Cocok untuk analisis mendalam atau report teknis.',
            self::Eksekutif => 'Tulis ringkas dan langsung ke inti. Fokus pada KPI, bottom-line impact, dan rekomendasi strategis. Gunakan bullet points. Cocok untuk CEO/direktur yang ingin snapshot cepat dalam 1 menit.',
            self::Storytelling => 'Tulis seperti cerita dengan alur naratif yang mengalir. Gunakan data sebagai "plot point" dan bangun tension/klimaks di insight utama. Cocok untuk presentasi ke audiens non-teknis agar data terasa hidup.',
            self::Akademis => 'Gunakan gaya penulisan ilmiah. Sertakan referensi metodologi, confidence level, dan batasan analisis. Gunakan terminologi statistik yang tepat. Cocok untuk skripsi, jurnal, atau laporan penelitian.',
        };
    }

    /**
     * Icon emoji untuk UI
     */
    public function icon(): string
    {
        return match ($this) {
            self::Formal => '👔',
            self::Santai => '☕',
            self::Teknis => '🔧',
            self::Eksekutif => '📌',
            self::Storytelling => '📖',
            self::Akademis => '🎓',
        };
    }

    /**
     * Deskripsi singkat untuk help text
     */
    public function description(): string
    {
        return match ($this) {
            self::Formal => 'Bahasa profesional untuk laporan bisnis',
            self::Santai => 'Bahasa ringkas dan mudah dipahami',
            self::Teknis => 'Bahasa teknis dengan detail analitis',
            self::Eksekutif => 'Ringkasan eksekutif 1 menit, langsung ke inti',
            self::Storytelling => 'Data diceritakan seperti narasi yang mengalir',
            self::Akademis => 'Gaya ilmiah untuk riset dan skripsi',
        };
    }

    /**
     * Contoh gaya penulisan untuk preview
     */
    public function example(): string
    {
        return match ($this) {
            self::Formal => 'Berdasarkan analisis data, ditemukan pertumbuhan sebesar 15% pada kuartal ini.',
            self::Santai => 'Penjualan kita naik lumayan — 15% dibanding bulan lalu!',
            self::Teknis => 'Tren linear menunjukkan pertumbuhan YoY sebesar 15% dengan R² = 0.89.',
            self::Eksekutif => '• Revenue +15% QoQ → rekomendasi: scale marketing budget 20%.',
            self::Storytelling => 'Dari 10.000 transaksi yang kami telusuri, sebuah pola menarik mulai terungkap...',
            self::Akademis => 'Analisis regresi menunjukkan korelasi signifikan (p < 0.05) antara variabel X dan Y.',
        };
    }
}
