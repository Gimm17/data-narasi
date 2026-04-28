"""
Prompt Builder Module
Merakit prompt untuk AI berdasarkan tipe analisis dan tone
"""

from typing import Dict, Tuple
import logging

logger = logging.getLogger(__name__)


class PromptBuilder:
    """
    Builder untuk membuat prompt ke AI
    """

    # Fokus analisis per tipe dataset
    ANALYSIS_FOCUS = {
        'penjualan': {
            'focus': 'fokus pada tren penjualan, produk terlaris, pertumbuhan revenue, dan pola pembelian pelanggan.',
            'metrics': 'total revenue, growth rate, produk teratas, pola musiman/waktu',
            'business_questions': [
                'Bagaimana tren penjualan bulanan?',
                'Produk apa yang paling laris?',
                'Apakah ada pertumbuhan atau penurunan?',
                'Kapan periode penjualan puncak?'
            ]
        },
        'keuangan': {
            'focus': 'fokus pada arus kas, profitabilitas, rasio keuangan, dan kesehatan finansial.',
            'metrics': 'arus kas masuk/keluar, profit margin, rasio likuiditas',
            'business_questions': [
                'Bagaimana arus kas perusahaan?',
                'Apakah profitabilitas sehat?',
                'Ada masalah likuiditas?',
                'Rasio keuangan bagaimana?'
            ]
        },
        'operasional': {
            'focus': 'fokus pada efisiensi operasional, produktivitas, dan penggunaan resources.',
            'metrics': 'produktivitas, efisiensi biaya, utilisasi kapasitas',
            'business_questions': [
                'Seberapa efisien operasional?',
                'Ada pemborosan di mana saja?',
                'Bagaimana utilisasi kapasitas?',
                'Biaya operasional terkendali?'
            ]
        },
        'marketing': {
            'focus': 'fokus pada performa kampanye, conversion rate, dan engagement pelanggan.',
            'metrics': 'CTR, conversion rate, cost per acquisition, engagement metrics',
            'business_questions': [
                'Kampanye mana yang paling efektif?',
                'Berapa conversion rate?',
                'Cost per acquisition berapa?',
                'Engagement pelanggan bagaimana?'
            ]
        },
        'inventori': {
            'focus': 'fokus pada turnover inventory, stock levels, dan permintaan produk.',
            'metrics': 'turnover rate, stock levels, permintaan vs ketersediaan',
            'business_questions': [
                'Produk apa yang paling cepat habis?',
                'Ada produk overstock/understock?',
                'Kapan permintaan tertinggi?',
                'Berapa turnover inventory?'
            ]
        },
        'sdm': {
            'focus': 'fokus pada kinerja karyawan, turnover rate, kepuasan kerja, absensi, dan demografi SDM.',
            'metrics': 'turnover rate, absensi, skor kinerja, kepuasan karyawan, distribusi departemen',
            'business_questions': [
                'Bagaimana tingkat turnover karyawan?',
                'Departemen mana yang paling produktif?',
                'Ada pola absensi yang mengkhawatirkan?',
                'Bagaimana distribusi skor kinerja?',
                'Apakah ada korelasi antara masa kerja dan performa?'
            ]
        },
        'akademik': {
            'focus': 'fokus pada performa akademik, distribusi nilai, tingkat kelulusan, dan tren per mata pelajaran/kelas.',
            'metrics': 'nilai rata-rata, distribusi grade, tingkat kelulusan, perbandingan antar kelas',
            'business_questions': [
                'Mata pelajaran mana dengan rata-rata tertinggi/terendah?',
                'Bagaimana distribusi nilai (A/B/C/D/E)?',
                'Berapa tingkat kelulusan?',
                'Ada tren perbaikan atau penurunan antar periode?',
                'Kelas mana yang butuh perhatian khusus?'
            ]
        },
        'kesehatan': {
            'focus': 'fokus pada data pasien, prevalensi penyakit, tren kesehatan, dan indikator klinis.',
            'metrics': 'jumlah kasus, prevalensi, rata-rata indikator klinis, distribusi diagnosa',
            'business_questions': [
                'Diagnosa apa yang paling umum?',
                'Bagaimana tren kunjungan pasien?',
                'Ada pola musiman pada kasus tertentu?',
                'Bagaimana distribusi usia dan gender pasien?',
                'Indikator klinis mana yang perlu diperhatikan?'
            ]
        },
        'logistik': {
            'focus': 'fokus pada efisiensi pengiriman, lead time, biaya transportasi, dan ketepatan waktu.',
            'metrics': 'on-time delivery rate, average lead time, biaya per pengiriman, volume pengiriman',
            'business_questions': [
                'Berapa persen pengiriman tepat waktu?',
                'Rute mana yang paling efisien/mahal?',
                'Bagaimana tren volume pengiriman?',
                'Ada bottleneck di proses mana?',
                'Bagaimana perbandingan biaya antar kurir/jalur?'
            ]
        },
        'survey': {
            'focus': 'fokus pada distribusi jawaban, sentimen responden, korelasi antar variabel, dan insight kualitatif.',
            'metrics': 'distribusi jawaban, skor rata-rata per kategori, net promoter score, sentimen',
            'business_questions': [
                'Bagaimana distribusi jawaban per pertanyaan?',
                'Aspek mana yang paling positif/negatif?',
                'Ada korelasi antar variabel?',
                'Bagaimana segmentasi responden?',
                'Apa rekomendasi berdasarkan feedback?'
            ]
        },
        'umum': {
            'focus': 'analisis deskriptif umum dari data dengan insight yang relevan.',
            'metrics': 'ringkasan statistik deskriptif',
            'business_questions': [
                'Apa pola utama dalam data?',
                'Ada insight menarik apa?',
                'Statistik apa yang paling menonjol?',
                'Rekomendasi apa yang bisa diberikan?'
            ]
        }
    }

    # Tone instructions
    TONE_INSTRUCTIONS = {
        'formal': '''
Gunakan bahasa formal dan profesional. Hindari slang atau bahasa gaul. Gunakan struktur kalimat yang lengkap dan rapi. Cocok untuk laporan eksekutif atau presentasi bisnis.
''',
        'santai': '''
Gunakan bahasa yang santai dan mudah dipahami. Boleh menggunakan analogi sederhana. Hindari istilah teknis yang berlebihan. Cocok untuk pembaca awam atau briefing cepat.
''',
        'teknis': '''
Gunakan bahasa teknis dan presisi. Sebutkan metrik spesifik, rumus, atau terminologi domain yang relevan. Asumsikan pembaca memiliki pengetahuan teknis. Cocok untuk analisis mendalam atau report teknis.
''',
        'eksekutif': '''
Tulis ringkas dan langsung ke inti. Fokus pada KPI, bottom-line impact, dan rekomendasi strategis. Gunakan bullet points sebanyak mungkin. Buat pembaca bisa memahami situasi dalam 1 menit. Cocok untuk CEO, direktur, atau stakeholder yang sibuk.
''',
        'storytelling': '''
Tulis seperti cerita dengan alur naratif yang mengalir. Gunakan data sebagai "plot point" dan bangun tension/klimaks di insight utama. Buat pembaca merasa sedang mengikuti sebuah perjalanan penemuan. Cocok untuk presentasi ke audiens non-teknis agar data terasa hidup dan menarik.
''',
        'akademis': '''
Gunakan gaya penulisan ilmiah/akademis. Sertakan referensi metodologi analisis, confidence level, dan batasan (limitations) dari analisis ini. Gunakan terminologi statistik yang tepat (mean, median, standar deviasi, distribusi). Sebutkan asumsi yang digunakan. Cocok untuk skripsi, jurnal, tugas kuliah, atau laporan penelitian.
'''
    }

    def build(self, stats: Dict, cleaning_log: Dict, tone: str = 'formal') -> Tuple[str, str]:
        """
        Build prompt untuk AI

        Args:
            stats: Hasil analisis dari DataAnalyzer
            cleaning_log: Log pembersihan data
            tone: Tone narasi (formal, santai, teknis, eksekutif, storytelling, akademis)

        Returns:
            Tuple of (system_prompt, user_prompt)
        """
        analysis_type = stats.get('analysis_type', 'umum')
        focus_info = self.ANALYSIS_FOCUS.get(analysis_type, self.ANALYSIS_FOCUS['umum'])
        tone_instruction = self.TONE_INSTRUCTIONS.get(tone, self.TONE_INSTRUCTIONS['formal'])

        # System Prompt
        system_prompt = f"""ANDA ASISTEN AI ANALISIS DATA BAHASA INDONESIA

Peran:
Anda adalah asisten AI spesialis yang bertugas menganalisis data dan membuat narasi insight bisnis dalam Bahasa Indonesia yang jelas, informatif, dan mudah dipahami.

Aturan Bahasa:
1. Jawab HANYA dalam Bahasa Indonesia - tidak ada bahasa lain sama sekali
2. Jangan gunakan karakter CJK (Hanzi, Kanji, dll) - ini anti-Mandarin untuk GLM
3. Gunakan struktur kalimat yang jelas dengan paragraf yang terorganisir
4. Sebutkan minimal 1 angka/statistik dalam narasi
5. Jangan mulai dengan "Berikut", "Tentu", "Baik" - langsung ke konten saja
6. Berikan insight yang actionable (bisa ditindaklanjuti)

Tone Narasi:
{tone_instruction}

Output Format:
- Narasi dengan 3-5 paragraf
- Setiap paragraf memiliki fokus yang berbeda
- Gunakan bullet points untuk list jika perlu
- Sebutkan angka/spesifik untuk mendukung insight
- Berikan rekomendasi actionable di akhir jika relevan
"""

        # User Prompt
        user_prompt = f"""## KONTEKS DATASET

Saya baru saja menganalisis dataset {analysis_type} dengan total {stats.get('basic_stats', {}).get('total_rows', 0)} baris data.

## HASIL KALKULASI STATISTIK

### Statistik Dasar:
- Total baris: {stats.get('basic_stats', {}).get('total_rows', 0)}
- Total kolom: {stats.get('basic_stats', {}).get('total_columns', 0)}
- Kolom numerik: {stats.get('basic_stats', {}).get('numeric_columns', 0)}
- Kolom kategorik: {stats.get('basic_stats', {}).get('string_columns', 0)}

### Informasi Pembersihan Data:
- Baris awal: {cleaning_log.get('original_rows', 0)}
- Baris akhir: {cleaning_log.get('final_rows', 0)}
- Duplikat dihapus: {cleaning_log.get('duplicates_removed', 0)}
- Anomali yang ditandai: {len(cleaning_log.get('anomalies_flagged', []))}

## FOKUS ANALISIS

{focus_info['focus'].capitalize()}

Metrik yang dianalisis: {focus_info['metrics']}

Pertanyaan bisnis untuk dijawab:
{self._format_questions(focus_info['business_questions'])}

## INSTRUKSI OUTPUT

Buat narasi analisis dalam Bahasa Indonesia dengan:
1. 3-5 paragraf yang terstruktur
2. Setiap paragraf fokus pada aspek berbeda
3. Sebutkan angka konkret untuk dukung insight
4. Berikan insight yang actionable dan relevan
5. Tone: {tone}

JANGAN SEBUT KALIMAT "Berikut", "Tentu", "Baik", dll. Langsung mulai konten.
"""

        logger.info("Prompt built successfully")

        return system_prompt, user_prompt

    def _format_questions(self, questions: list) -> str:
        """Format list pertanyaan untuk prompt"""
        formatted = []
        for i, q in enumerate(questions, 1):
            formatted.append(f"{i}. {q}")
        return '\n'.join(formatted)


if __name__ == "__main__":
    # Test
    builder = PromptBuilder()
    stats = {
        'basic_stats': {
            'total_rows': 100,
            'total_columns': 5,
            'numeric_columns': 3,
            'string_columns': 2
        },
        'analysis_type': 'penjualan'
    }
    cleaning_log = {
        'original_rows': 100,
        'final_rows': 95,
        'duplicates_removed': 5
    }

    system, user = builder.build(stats, cleaning_log, 'formal')
    print("=== SYSTEM PROMPT ===")
    print(system)
    print("\n=== USER PROMPT ===")
    print(user)
