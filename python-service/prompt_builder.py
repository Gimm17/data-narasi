"""
Prompt Builder Module v2
Data-rich prompts — sends actual aggregated data to AI for Claude-quality insights
"""

from typing import Dict, Tuple, List, Any
import logging
import json

logger = logging.getLogger(__name__)


class PromptBuilder:
    """Build data-rich prompts for AI narrative generation"""

    ANALYSIS_FOCUS = {
        'penjualan': {
            'focus': 'tren penjualan, produk terlaris, pertumbuhan revenue, pola musiman, segmentasi pelanggan, dan distribusi harga.',
            'metrics': 'total revenue, growth rate YoY, produk/kategori teratas, average order value, median vs mean (skewness), seasonal patterns',
        },
        'keuangan': {
            'focus': 'arus kas, profitabilitas, rasio keuangan, tren pengeluaran, dan kesehatan finansial.',
            'metrics': 'net cash flow, profit margin, expense ratio, tren bulanan, top cost centers',
        },
        'operasional': {
            'focus': 'efisiensi operasional, produktivitas, utilisasi kapasitas, dan bottleneck.',
            'metrics': 'achievement rate, produktivitas per unit, cycle time, capacity utilization',
        },
        'marketing': {
            'focus': 'performa kampanye, conversion funnel, ROI per channel, dan engagement metrics.',
            'metrics': 'CTR, conversion rate, CPA, ROAS, engagement per platform',
        },
        'inventori': {
            'focus': 'turnover inventory, stock levels, dead stock, dan demand forecasting.',
            'metrics': 'inventory turnover, stockout risk, overstock items, total inventory value',
        },
        'sdm': {
            'focus': 'kinerja karyawan, turnover rate, distribusi kompensasi, dan workforce analytics.',
            'metrics': 'turnover rate, avg performance score, salary distribution, headcount per dept',
        },
        'akademik': {
            'focus': 'performa akademik, distribusi nilai, gap analysis, dan tren per periode.',
            'metrics': 'average grade, pass rate, grade distribution, subject comparison',
        },
        'kesehatan': {
            'focus': 'prevalensi penyakit, demografi pasien, tren kunjungan, dan indikator klinis.',
            'metrics': 'top diagnoses, age/gender distribution, vital sign averages, visit trends',
        },
        'logistik': {
            'focus': 'efisiensi pengiriman, on-time delivery, biaya transportasi, dan route optimization.',
            'metrics': 'OTD rate, avg delivery time, cost per shipment, top routes',
        },
        'survey': {
            'focus': 'distribusi jawaban, sentimen, NPS, dan korelasi antar variabel survei.',
            'metrics': 'avg rating, NPS, satisfaction distribution, top/bottom aspects',
        },
        'umum': {
            'focus': 'pola utama, distribusi data, korelasi antar variabel, dan anomali yang ditemukan.',
            'metrics': 'deskriptif statistik, distribusi, korelasi, outlier',
        }
    }

    TONE_INSTRUCTIONS = {
        'formal': 'Gunakan bahasa formal dan profesional. Struktur kalimat lengkap dan rapi. Cocok untuk laporan eksekutif.',
        'santai': 'Gunakan bahasa santai dan mudah dipahami. Boleh gunakan analogi sederhana. Cocok untuk briefing cepat.',
        'teknis': 'Gunakan bahasa teknis dan presisi. Sebutkan metrik spesifik, terminologi domain. Cocok untuk report teknis.',
        'eksekutif': 'Tulis ringkas dan langsung ke inti. Fokus KPI, bottom-line impact, rekomendasi strategis. Format bullet points.',
        'storytelling': 'Tulis seperti cerita dengan alur naratif. Data sebagai plot point. Bangun tension di insight utama.',
        'akademis': 'Gaya penulisan ilmiah. Sertakan metodologi, confidence level, batasan analisis. Terminologi statistik tepat.',
    }

    def build(self, stats: Dict, cleaning_log: Dict, tone: str = 'formal') -> Tuple[str, str]:
        analysis_type = stats.get('analysis_type', 'umum')
        focus_info = self.ANALYSIS_FOCUS.get(analysis_type, self.ANALYSIS_FOCUS['umum'])
        tone_inst = self.TONE_INSTRUCTIONS.get(tone, self.TONE_INSTRUCTIONS['formal'])

        system_prompt = f"""ANDA ADALAH ANALIS DATA SENIOR BERBAHASA INDONESIA

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

TONE: {tone_inst}

FORMAT OUTPUT (5-8 paragraf):
1. **Overview & Ringkasan Eksekutif** — gambaran besar dataset dan temuan utama
2. **Analisis Tren & Pola** — temporal trends, seasonal patterns, growth rates
3. **Breakdown per Segmen/Kategori** — perbandingan antar kelompok, siapa yang dominan
4. **Distribusi & Anomali** — skewness, outlier, gap mean-median, data quality issues
5. **Korelasi & Hubungan Antar Variabel** — apa yang saling mempengaruhi
6. **Rekomendasi Strategis** — minimal 3 actionable recommendations dengan konteks data

Gunakan bullet points, angka spesifik, dan persentase. Setiap paragraf minimal 3 kalimat."""

        # Build data-rich user prompt
        user_prompt = self._build_user_prompt(stats, cleaning_log, analysis_type, focus_info)
        logger.info("Prompt built successfully (data-rich v2)")
        return system_prompt, user_prompt

    def _build_user_prompt(self, stats: Dict, cleaning_log: Dict, analysis_type: str, focus_info: Dict) -> str:
        basic = stats.get('basic_stats', {})
        enriched = stats.get('enriched', {})
        column_info = stats.get('column_info', {})

        sections = []

        # Section 1: Dataset overview
        sections.append(f"""## KONTEKS DATASET
Tipe analisis: {analysis_type}
Total baris: {basic.get('total_rows', 0):,}
Total kolom: {basic.get('total_columns', 0)}
Kolom numerik: {basic.get('numeric_columns', 0)}
Kolom kategorik: {basic.get('string_columns', 0)}
Memory: {basic.get('memory_usage_mb', 0)} MB""")

        # Section 2: Cleaning summary
        sections.append(f"""## PEMBERSIHAN DATA
Baris awal: {cleaning_log.get('original_rows', 0):,}
Baris akhir: {cleaning_log.get('final_rows', 0):,}
Duplikat dihapus: {cleaning_log.get('duplicates_removed', 0)}
Baris kosong dihapus: {cleaning_log.get('empty_rows_removed', 0)}
Total masalah: {cleaning_log.get('total_issues_found', 0)}""")

        # Null details
        nulls = cleaning_log.get('nulls_filled', {})
        if nulls:
            null_lines = []
            for col, info in list(nulls.items())[:8]:
                null_lines.append(f"- {col}: {info.get('count', 0)} null → {info.get('method', '?')} ({info.get('value', '?')})")
            sections.append("### Nilai Kosong yang Diisi:\n" + "\n".join(null_lines))

        # Section 3: Column statistics with enriched data
        num_stats_lines = []
        for col, info in column_info.items():
            if info.get('is_numeric'):
                line = f"- **{col}**: min={info.get('min')}, max={info.get('max')}, mean={info.get('mean')}, median={info.get('median')}, std={info.get('std')}"
                if info.get('skewness') is not None:
                    line += f", skewness={info['skewness']}"
                if info.get('distribution'):
                    line += f" [{info['distribution']}]"
                if info.get('outlier_count', 0) > 0:
                    line += f", {info['outlier_count']} outlier"
                if info.get('mean_median_ratio') and abs(info['mean_median_ratio'] - 1) > 0.3:
                    line += f" ⚠️ mean/median ratio={info['mean_median_ratio']} (distribusi tidak simetris!)"
                num_stats_lines.append(line)
        if num_stats_lines:
            sections.append("## STATISTIK KOLOM NUMERIK\n" + "\n".join(num_stats_lines[:10]))

        # Section 4: Categorical top values
        cat_lines = []
        for col, info in column_info.items():
            if not info.get('is_numeric') and info.get('top_values'):
                top = info['top_values']
                vals = ", ".join([f"{k}: {v}" for k, v in list(top.items())[:5]])
                cat_lines.append(f"- **{col}** ({info.get('unique_count', 0)} unique): {vals}")
        if cat_lines:
            sections.append("## DISTRIBUSI KOLOM KATEGORIK\n" + "\n".join(cat_lines[:8]))

        # Section 5: Enriched — temporal trends
        yearly = enriched.get('yearly_trend', [])
        if yearly:
            trend_lines = []
            for i, yr in enumerate(yearly):
                line = f"- {yr['year']}: total={yr['total']:,.2f}, count={yr['count']}, avg={yr['avg']:,.2f}"
                if i > 0:
                    prev = yearly[i-1]['total']
                    if prev > 0:
                        growth = (yr['total'] - prev) / prev * 100
                        line += f" ({'↑' if growth > 0 else '↓'} {growth:+.1f}%)"
                trend_lines.append(line)
            sections.append("## TREN TEMPORAL (TAHUNAN)\n" + "\n".join(trend_lines))
            if enriched.get('peak_month'):
                sections.append(f"Peak month: {enriched['peak_month']} (nilai: {enriched.get('peak_month_value', 0):,.2f})")

        # Section 6: Enriched — categorical breakdowns
        for key, val in enriched.items():
            if key.endswith('_breakdown') and isinstance(val, list):
                cat_name = key.replace('_breakdown', '')
                bd_lines = []
                for item in val[:10]:
                    bd_lines.append(f"- {item['label']}: total={item['total']:,.2f} ({item['pct']:.1f}%), count={item['count']}, avg={item['avg']:,.2f}")
                sections.append(f"## BREAKDOWN: {cat_name.upper()}\n" + "\n".join(bd_lines))

        # Section 7: Correlations
        corrs = enriched.get('correlations', [])
        if corrs:
            corr_lines = []
            for c in corrs[:5]:
                direction = "positif" if c['r'] > 0 else "NEGATIF"
                strength = "kuat" if abs(c['r']) > 0.7 else ("sedang" if abs(c['r']) > 0.4 else "lemah")
                corr_lines.append(f"- {c['col1']} × {c['col2']}: r={c['r']} ({direction}, {strength})")
            sections.append("## KORELASI ANTAR VARIABEL\n" + "\n".join(corr_lines))

        # Section 8: Outlier summary
        outliers = enriched.get('outlier_summary', [])
        if outliers:
            out_lines = []
            for o in outliers[:5]:
                out_lines.append(f"- {o['column']}: {o['count']} outlier ({o['pct']:.1f}%), max={o['max_outlier']:,.2f}")
            sections.append("## OUTLIER TERDETEKSI (IQR)\n" + "\n".join(out_lines))

        # Section 9: Domain-specific insights (from analyzer)
        domain_keys = ['sales_insights', 'finance_insights', 'operational_insights',
                       'marketing_insights', 'inventory_insights', 'sdm_insights',
                       'akademik_insights', 'kesehatan_insights', 'logistik_insights',
                       'survey_insights', 'generic_insights']
        for dk in domain_keys:
            if dk in stats and stats[dk]:
                sections.append(f"## INSIGHT DOMAIN ({dk.upper()})\n{json.dumps(stats[dk], indent=2, default=str)[:2000]}")
                break

        # Section 10: Focus instruction
        sections.append(f"""## FOKUS ANALISIS
{focus_info['focus']}
Metrik kunci: {focus_info['metrics']}

## INSTRUKSI
Buat narasi analisis mendalam dalam Bahasa Indonesia. GUNAKAN SEMUA DATA DI ATAS.
Sebutkan angka spesifik, persentase, perbandingan. Identifikasi anomali dan pola.
Berikan minimal 3 rekomendasi strategis yang actionable berdasarkan temuan data.""")

        return "\n\n".join(sections)


if __name__ == "__main__":
    builder = PromptBuilder()
    stats = {
        'basic_stats': {'total_rows': 9800, 'total_columns': 18, 'numeric_columns': 5, 'string_columns': 13},
        'analysis_type': 'penjualan',
        'column_info': {},
        'enriched': {},
    }
    cleaning_log = {'original_rows': 9800, 'final_rows': 9800, 'duplicates_removed': 0}
    system, user = builder.build(stats, cleaning_log, 'formal')
    print(f"System: {len(system)} chars")
    print(f"User: {len(user)} chars")
