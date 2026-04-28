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

    /**
     * Label untuk tampilan UI
     */
    public function label(): string
    {
        return match ($this) {
            self::Formal => 'Formal',
            self::Santai => 'Santai',
            self::Teknis => 'Teknis',
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
        };
    }

    /**
     * Icon emoji untuk UI
     */
    public function icon(): string
    {
        return match ($this) {
            self::Formal => '👔',
            self::Santai => '😊',
            self::Teknis => '🔧',
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
        };
    }

    /**
     * Contoh gaya penulisan untuk preview
     */
    public function example(): string
    {
        return match ($this) {
            self::Formal => 'Berdasarkan analisis data, ditemukan pertumbuhan sebesar 15% pada kuartal ini.',
            self::Santai => 'Lumayan banget! Penjualan kita naik 15% bulan ini.',
            self::Teknis => 'Tren linear menunjukkan pertumbuhan YoY sebesar 15% dengan R² = 0.89.',
        };
    }
}
