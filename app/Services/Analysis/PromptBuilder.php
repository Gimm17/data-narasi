<?php

namespace App\Services\Analysis;

use Illuminate\Support\Facades\Log;

/**
 * PromptBuilder — port PHP dari python-service/prompt_builder.py
 *
 * Struktur prompt (system + user) dipertahankan identik agar kualitas
 * narasi yang dihasilkan tidak berubah setelah pindah dari Python.
 */
class PromptBuilder
{
    /** Fokus analisis per tipe dataset. */
    private const ANALYSIS_FOCUS = [
        'penjualan' => [
            'focus' => 'tren penjualan, produk terlaris, pertumbuhan revenue, pola musiman, segmentasi pelanggan, dan distribusi harga.',
            'metrics' => 'total revenue, growth rate YoY, produk/kategori teratas, average order value, median vs mean (skewness), seasonal patterns',
        ],
        'keuangan' => [
            'focus' => 'arus kas, profitabilitas, rasio keuangan, tren pengeluaran, dan kesehatan finansial.',
            'metrics' => 'net cash flow, profit margin, expense ratio, tren bulanan, top cost centers',
        ],
        'operasional' => [
            'focus' => 'efisiensi operasional, produktivitas, utilisasi kapasitas, dan bottleneck.',
            'metrics' => 'achievement rate, produktivitas per unit, cycle time, capacity utilization',
        ],
        'marketing' => [
            'focus' => 'performa kampanye, conversion funnel, ROI per channel, dan engagement metrics.',
            'metrics' => 'CTR, conversion rate, CPA, ROAS, engagement per platform',
        ],
        'inventori' => [
            'focus' => 'turnover inventory, stock levels, dead stock, dan demand forecasting.',
            'metrics' => 'inventory turnover, stockout risk, overstock items, total inventory value',
        ],
        'sdm' => [
            'focus' => 'kinerja karyawan, turnover rate, distribusi kompensasi, dan workforce analytics.',
            'metrics' => 'turnover rate, avg performance score, salary distribution, headcount per dept',
        ],
        'akademik' => [
            'focus' => 'performa akademik, distribusi nilai, gap analysis, dan tren per periode.',
            'metrics' => 'average grade, pass rate, grade distribution, subject comparison',
        ],
        'kesehatan' => [
            'focus' => 'prevalensi penyakit, demografi pasien, tren kunjungan, dan indikator klinis.',
            'metrics' => 'top diagnoses, age/gender distribution, vital sign averages, visit trends',
        ],
        'logistik' => [
            'focus' => 'efisiensi pengiriman, on-time delivery, biaya transportasi, dan route optimization.',
            'metrics' => 'OTD rate, avg delivery time, cost per shipment, top routes',
        ],
        'survey' => [
            'focus' => 'distribusi jawaban, sentimen, NPS, dan korelasi antar variabel survei.',
            'metrics' => 'avg rating, NPS, satisfaction distribution, top/bottom aspects',
        ],
        'umum' => [
            'focus' => 'pola utama, distribusi data, korelasi antar variabel, dan anomali yang ditemukan.',
            'metrics' => 'deskriptif statistik, distribusi, korelasi, outlier',
        ],
    ];

    /** Instruksi tone penulisan. */
    private const TONE_INSTRUCTIONS = [
        'formal' => 'Gunakan bahasa formal dan profesional. Struktur kalimat lengkap dan rapi. Cocok untuk laporan eksekutif.',
        'santai' => 'Gunakan bahasa santai dan mudah dipahami. Boleh gunakan analogi sederhana. Cocok untuk briefing cepat.',
        'teknis' => 'Gunakan bahasa teknis dan presisi. Sebutkan metrik spesifik, terminologi domain. Cocok untuk report teknis.',
        'eksekutif' => 'Tulis ringkas dan langsung ke inti. Fokus KPI, bottom-line impact, rekomendasi strategis. Format bullet points.',
        'storytelling' => 'Tulis seperti cerita dengan alur naratif. Data sebagai plot point. Bangun tension di insight utama.',
        'akademis' => 'Gaya penulisan ilmiah. Sertakan metodologi, confidence level, batasan analisis. Terminologi statistik tepat.',
    ];

    /** Kunci insight domain yang dikirim ke AI, sesuai urutan prioritas. */
    private const DOMAIN_KEYS = [
        'sales_insights',
        'finance_insights',
        'operational_insights',
        'marketing_insights',
        'inventory_insights',
        'sdm_insights',
        'akademik_insights',
        'kesehatan_insights',
        'logistik_insights',
        'survey_insights',
        'generic_insights',
    ];

    /**
     * Bangun pasangan system prompt dan user prompt.
     *
     * @param array<string, mixed> $stats keluaran DataAnalyzer
     * @param array<string, mixed> $cleaningLog keluaran DataCleaner
     * @return array{0: string, 1: string} [system_prompt, user_prompt]
     */
    public function build(array $stats, array $cleaningLog, string $tone = 'formal'): array
    {
        $analysisType = $stats['analysis_type'] ?? 'umum';
        $focus = self::ANALYSIS_FOCUS[$analysisType] ?? self::ANALYSIS_FOCUS['umum'];
        $toneInstruction = self::TONE_INSTRUCTIONS[$tone] ?? self::TONE_INSTRUCTIONS['formal'];

        $systemPrompt = <<<PROMPT
            ANDA ADALAH ANALIS DATA SENIOR BERBAHASA INDONESIA

            PERAN: Anda analis data senior yang menghasilkan insight bisnis berkualitas tinggi dari data statistik. Anda menganalisis dengan kedalaman setara konsultan McKinsey/BCG — bukan sekadar membaca angka, tapi menginterpretasi makna bisnis di baliknya.

            ATURAN KETAT:
            1. Jawab HANYA dalam Bahasa Indonesia — tidak ada bahasa lain
            2. JANGAN gunakan karakter CJK (Hanzi/Kanji)
            3. JANGAN mulai dengan "Berikut", "Tentu", "Baik" — langsung ke konten
            4. Setiap klaim HARUS didukung angka spesifik dari data
            5. Bandingkan mean vs median — jika gap besar, jelaskan implikasi (distribusi skewed)
            6. Identifikasi anomali, outlier, dan pola yang tidak biasa
            7. Berikan rekomendasi yang ACTIONABLE dan SPESIFIK

            KUALITAS INSIGHT YANG DIHARAPKAN:
            - Jangan hanya menyebut "penjualan naik" — sebutkan berapa persen, dari berapa ke berapa
            - Jangan hanya menyebut "ada outlier" — jelaskan dampaknya terhadap rata-rata
            - Bandingkan antar kategori/segmen — mana yang overperform vs underperform
            - Identifikasi pola temporal (musiman, tren naik/turun, anomali periode tertentu)
            - Jika ada korelasi kuat, jelaskan implikasi bisnis/praktisnya

            TONE: {$toneInstruction}

            FORMAT OUTPUT (5-8 paragraf):
            1. **Overview & Ringkasan Eksekutif** — gambaran besar dataset dan temuan utama
            2. **Analisis Tren & Pola** — temporal trends, seasonal patterns, growth rates
            3. **Breakdown per Segmen/Kategori** — perbandingan antar kelompok, siapa yang dominan
            4. **Distribusi & Anomali** — skewness, outlier, gap mean-median, data quality issues
            5. **Korelasi & Hubungan Antar Variabel** — apa yang saling mempengaruhi
            6. **Rekomendasi Strategis** — minimal 3 actionable recommendations dengan konteks data

            Gunakan bullet points, angka spesifik, dan persentase. Setiap paragraf minimal 3 kalimat.
            PROMPT;

        $userPrompt = $this->buildUserPrompt($stats, $cleaningLog, $analysisType, $focus);
        Log::info('Prompt berhasil dibangun', ['chars' => strlen($systemPrompt) + strlen($userPrompt)]);

        return [$systemPrompt, $userPrompt];
    }

    /**
     * Susun user prompt berisi 10 bagian data.
     *
     * @param array<string, mixed> $stats
     * @param array<string, mixed> $cleaningLog
     * @param array{focus: string, metrics: string} $focus
     */
    private function buildUserPrompt(array $stats, array $cleaningLog, string $analysisType, array $focus): string
    {
        $basic = $stats['basic_stats'] ?? [];
        $enriched = $stats['enriched'] ?? [];
        $columnInfo = $stats['column_info'] ?? [];

        $sections = [];

        // 1. Ringkasan dataset
        $sections[] = sprintf(
            "## KONTEKS DATASET\nTipe analisis: %s\nTotal baris: %s\nTotal kolom: %s\nKolom numerik: %s\nKolom kategorik: %s\nMemory: %s MB",
            $analysisType,
            $this->num($basic['total_rows'] ?? 0, 0),
            $basic['total_columns'] ?? 0,
            $basic['numeric_columns'] ?? 0,
            $basic['string_columns'] ?? 0,
            $basic['memory_usage_mb'] ?? 0
        );

        // 2. Ringkasan pembersihan
        $sections[] = sprintf(
            "## PEMBERSIHAN DATA\nBaris awal: %s\nBaris akhir: %s\nDuplikat dihapus: %s\nBaris kosong dihapus: %s\nTotal masalah: %s",
            $this->num($cleaningLog['original_rows'] ?? 0, 0),
            $this->num($cleaningLog['final_rows'] ?? 0, 0),
            $cleaningLog['duplicates_removed'] ?? 0,
            $cleaningLog['empty_rows_removed'] ?? 0,
            $cleaningLog['total_issues_found'] ?? 0
        );

        $nulls = $cleaningLog['nulls_filled'] ?? [];
        if ($nulls !== []) {
            $lines = [];
            foreach (array_slice($nulls, 0, 8, true) as $col => $info) {
                $lines[] = sprintf(
                    '- %s: %s null → %s (%s)',
                    $col,
                    $info['count'] ?? 0,
                    $info['method'] ?? '?',
                    $info['value'] ?? '?'
                );
            }
            $sections[] = "### Nilai Kosong yang Diisi:\n" . implode("\n", $lines);
        }

        // 3. Statistik kolom numerik
        $numericLines = [];
        foreach ($columnInfo as $col => $info) {
            if (empty($info['is_numeric'])) {
                continue;
            }

            $line = sprintf(
                '- **%s**: min=%s, max=%s, mean=%s, median=%s, std=%s',
                $col,
                $info['min'] ?? 'n/a',
                $info['max'] ?? 'n/a',
                $info['mean'] ?? 'n/a',
                $info['median'] ?? 'n/a',
                $info['std'] ?? 'n/a'
            );

            if (isset($info['skewness'])) {
                $line .= ', skewness=' . $info['skewness'];
            }
            if (!empty($info['distribution'])) {
                $line .= " [{$info['distribution']}]";
            }
            if (($info['outlier_count'] ?? 0) > 0) {
                $line .= ", {$info['outlier_count']} outlier";
            }
            if (!empty($info['mean_median_ratio']) && abs($info['mean_median_ratio'] - 1) > 0.3) {
                $line .= " ⚠️ mean/median ratio={$info['mean_median_ratio']} (distribusi tidak simetris!)";
            }

            $numericLines[] = $line;
        }
        if ($numericLines !== []) {
            $sections[] = "## STATISTIK KOLOM NUMERIK\n" . implode("\n", array_slice($numericLines, 0, 10));
        }

        // 4. Distribusi kolom kategorik
        $catLines = [];
        foreach ($columnInfo as $col => $info) {
            if (!empty($info['is_numeric']) || empty($info['top_values'])) {
                continue;
            }

            $parts = [];
            foreach (array_slice($info['top_values'], 0, 5, true) as $value => $count) {
                $parts[] = "{$value}: {$count}";
            }
            $catLines[] = sprintf('- **%s** (%s unique): %s', $col, $info['unique_count'] ?? 0, implode(', ', $parts));
        }
        if ($catLines !== []) {
            $sections[] = "## DISTRIBUSI KOLOM KATEGORIK\n" . implode("\n", array_slice($catLines, 0, 8));
        }

        // 5. Tren temporal
        $yearly = $enriched['yearly_trend'] ?? [];
        if ($yearly !== []) {
            $trendLines = [];
            foreach ($yearly as $i => $year) {
                $line = sprintf(
                    '- %s: total=%s, count=%s, avg=%s',
                    $year['year'],
                    $this->num($year['total'], 2),
                    $year['count'],
                    $this->num($year['avg'], 2)
                );

                if ($i > 0) {
                    $prev = $yearly[$i - 1]['total'];
                    if ($prev > 0) {
                        $growth = ($year['total'] - $prev) / $prev * 100;
                        $arrow = $growth > 0 ? '↑' : '↓';
                        $line .= sprintf(' (%s %+.1f%%)', $arrow, $growth);
                    }
                }

                $trendLines[] = $line;
            }

            $sections[] = "## TREN TEMPORAL (TAHUNAN)\n" . implode("\n", $trendLines);

            if (!empty($enriched['peak_month'])) {
                $sections[] = sprintf(
                    'Peak month: %s (nilai: %s)',
                    $enriched['peak_month'],
                    $this->num($enriched['peak_month_value'] ?? 0, 2)
                );
            }
        }

        // 6. Breakdown kategori
        foreach ($enriched as $key => $value) {
            if (!str_ends_with((string) $key, '_breakdown') || !is_array($value)) {
                continue;
            }

            $catName = str_replace('_breakdown', '', (string) $key);
            $breakdownLines = [];
            foreach (array_slice($value, 0, 10) as $item) {
                $breakdownLines[] = sprintf(
                    '- %s: total=%s (%s%%), count=%s, avg=%s',
                    $item['label'],
                    $this->num($item['total'], 2),
                    $this->num($item['pct'], 1),
                    $item['count'],
                    $this->num($item['avg'], 2)
                );
            }

            $sections[] = '## BREAKDOWN: ' . mb_strtoupper($catName) . "\n" . implode("\n", $breakdownLines);
        }

        // 7. Korelasi
        $correlations = $enriched['correlations'] ?? [];
        if ($correlations !== []) {
            $correlationLines = [];
            foreach (array_slice($correlations, 0, 5) as $corr) {
                $direction = $corr['r'] > 0 ? 'positif' : 'NEGATIF';
                $strength = match (true) {
                    abs($corr['r']) > 0.7 => 'kuat',
                    abs($corr['r']) > 0.4 => 'sedang',
                    default => 'lemah',
                };
                $correlationLines[] = sprintf(
                    '- %s × %s: r=%s (%s, %s)',
                    $corr['col1'],
                    $corr['col2'],
                    $corr['r'],
                    $direction,
                    $strength
                );
            }
            $sections[] = "## KORELASI ANTAR VARIABEL\n" . implode("\n", $correlationLines);
        }

        // 8. Outlier
        $outliers = $enriched['outlier_summary'] ?? [];
        if ($outliers !== []) {
            $outlierLines = [];
            foreach (array_slice($outliers, 0, 5) as $outlier) {
                $outlierLines[] = sprintf(
                    '- %s: %s outlier (%s%%), max=%s',
                    $outlier['column'],
                    $outlier['count'],
                    $this->num($outlier['pct'], 1),
                    $this->num($outlier['max_outlier'], 2)
                );
            }
            $sections[] = "## OUTLIER TERDETEKSI (IQR)\n" . implode("\n", $outlierLines);
        }

        // 9. Insight domain
        foreach (self::DOMAIN_KEYS as $domainKey) {
            if (empty($stats[$domainKey])) {
                continue;
            }

            $json = json_encode($stats[$domainKey], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $sections[] = sprintf(
                "## INSIGHT DOMAIN (%s)\n%s",
                mb_strtoupper($domainKey),
                mb_substr((string) $json, 0, 2000)
            );
            break;
        }

        // 10. Fokus & instruksi
        $sections[] = sprintf(
            "## FOKUS ANALISIS\n%s\nMetrik kunci: %s\n\n## INSTRUKSI\nBuat narasi analisis mendalam dalam Bahasa Indonesia. GUNAKAN SEMUA DATA DI ATAS.\nSebutkan angka spesifik, persentase, perbandingan. Identifikasi anomali dan pola.\nBerikan minimal 3 rekomendasi strategis yang actionable berdasarkan temuan data.",
            $focus['focus'],
            $focus['metrics']
        );

        return implode("\n\n", $sections);
    }

    /**
     * Format angka gaya Indonesia dengan pemisah ribuan titik.
     */
    private function num(float|int|string|null $value, int $decimals = 0): string
    {
        return number_format((float) ($value ?? 0), $decimals, ',', '.');
    }

    /**
     * Daftar tipe analisis yang didukung (dipakai untuk validasi).
     *
     * @return array<int, string>
     */
    public static function supportedAnalysisTypes(): array
    {
        return array_keys(self::ANALYSIS_FOCUS);
    }

    /**
     * Daftar tone yang didukung (dipakai untuk validasi).
     *
     * @return array<int, string>
     */
    public static function supportedTones(): array
    {
        return array_keys(self::TONE_INSTRUCTIONS);
    }
}
