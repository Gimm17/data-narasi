"""
Data Analyzer Module
Menghitung statistik dan tren dari dataset
"""

import pandas as pd
import numpy as np
from typing import Dict, Any, List
import logging

logger = logging.getLogger(__name__)


def _find_cols(df: pd.DataFrame, keywords: List[str]) -> List[str]:
    """Helper: cari kolom berdasarkan keyword (case-insensitive)"""
    return [c for c in df.columns if any(k in c.lower() for k in keywords)]


def _safe_numeric_stats(series: pd.Series) -> Dict[str, float]:
    """Helper: hitung statistik numerik dengan error handling"""
    s = pd.to_numeric(series, errors='coerce').dropna()
    if s.empty:
        return {}
    return {
        'total': float(s.sum()),
        'mean': round(float(s.mean()), 2),
        'median': round(float(s.median()), 2),
        'min': float(s.min()),
        'max': float(s.max()),
        'std': round(float(s.std()), 2) if len(s) > 1 else 0,
    }


class DataAnalyzer:
    """Analisis statistik dengan Pandas"""

    def run(self, df: pd.DataFrame, analysis_type: str) -> Dict[str, Any]:
        logger.info(f"Starting statistical analysis (type: {analysis_type})")

        stats = {
            'basic_stats': self._basic_stats(df),
            'column_info': self._column_info(df),
            'analysis_type': analysis_type,
            'insights': [],
            'enriched': self._enriched_stats(df),
        }

        method_map = {
            'penjualan': self._analyze_sales,
            'keuangan': self._analyze_finance,
            'operasional': self._analyze_operational,
            'marketing': self._analyze_marketing,
            'inventori': self._analyze_inventory,
            'sdm': self._analyze_sdm,
            'akademik': self._analyze_akademik,
            'kesehatan': self._analyze_kesehatan,
            'logistik': self._analyze_logistik,
            'survey': self._analyze_survey,
        }

        analyzer = method_map.get(analysis_type, self._analyze_generic)
        try:
            stats.update(analyzer(df))
        except Exception as e:
            logger.warning(f"Domain analysis error for {analysis_type}: {e}")
            stats.update(self._analyze_generic(df))

        logger.info("✅ Statistical analysis complete!")
        return stats

    def _basic_stats(self, df: pd.DataFrame) -> Dict[str, Any]:
        return {
            'total_rows': len(df),
            'total_columns': len(df.columns),
            'numeric_columns': len(df.select_dtypes(include=[np.number]).columns),
            'string_columns': len(df.select_dtypes(include=['object']).columns),
            'memory_usage_mb': round(df.memory_usage(deep=True).sum() / (1024 * 1024), 2)
        }

    def _column_info(self, df: pd.DataFrame) -> Dict[str, Any]:
        column_info = {}
        for col in df.columns:
            col_info = {
                'name': col,
                'dtype': str(df[col].dtype),
                'null_count': int(df[col].isna().sum()),
                'null_percentage': round(float(df[col].isna().sum() / max(len(df), 1) * 100), 1),
                'unique_count': int(df[col].nunique())
            }
            if pd.api.types.is_numeric_dtype(df[col]):
                col_info['is_numeric'] = True
                s = df[col].dropna()
                col_info['min'] = float(s.min()) if not s.empty else None
                col_info['max'] = float(s.max()) if not s.empty else None
                col_info['mean'] = round(float(s.mean()), 2) if not s.empty else None
                col_info['median'] = round(float(s.median()), 2) if not s.empty else None
                col_info['std'] = round(float(s.std()), 2) if len(s) > 1 else None
                # Enriched: skewness, outlier count, distribution shape
                if len(s) > 3:
                    skew = float(s.skew())
                    col_info['skewness'] = round(skew, 2)
                    q1, q3 = s.quantile(0.25), s.quantile(0.75)
                    iqr = q3 - q1
                    outliers = ((s < q1 - 1.5 * iqr) | (s > q3 + 1.5 * iqr)).sum()
                    col_info['outlier_count'] = int(outliers)
                    col_info['mean_median_ratio'] = round(float(s.mean()) / max(float(s.median()), 0.001), 2)
                    if abs(skew) < 0.5:
                        col_info['distribution'] = 'normal'
                    elif skew > 1.5:
                        col_info['distribution'] = 'highly_right_skewed'
                    elif skew > 0.5:
                        col_info['distribution'] = 'right_skewed'
                    elif skew < -1.5:
                        col_info['distribution'] = 'highly_left_skewed'
                    else:
                        col_info['distribution'] = 'left_skewed'
            else:
                col_info['is_numeric'] = False
                col_info['sample_values'] = df[col].dropna().head(5).tolist()
                # Top value counts for categorical
                top = df[col].value_counts().head(5)
                col_info['top_values'] = {str(k): int(v) for k, v in top.items()}
            column_info[col] = col_info
        return column_info

    def _enriched_stats(self, df: pd.DataFrame) -> Dict[str, Any]:
        """Compute enriched statistics for prompt builder"""
        enriched = {}
        num_cols = df.select_dtypes(include=[np.number]).columns.tolist()
        cat_cols = df.select_dtypes(include=['object']).columns.tolist()

        # Top correlations
        if len(num_cols) >= 2:
            try:
                corr = df[num_cols].corr()
                pairs = []
                for i in range(len(num_cols)):
                    for j in range(i + 1, len(num_cols)):
                        v = corr.iloc[i, j]
                        if not np.isnan(v):
                            pairs.append({'col1': num_cols[i], 'col2': num_cols[j], 'r': round(float(v), 3)})
                pairs.sort(key=lambda x: abs(x['r']), reverse=True)
                enriched['correlations'] = pairs[:8]
            except:
                pass

        # Temporal trend (yearly/monthly if date col exists)
        date_cols = _find_cols(df, ['tanggal', 'date', 'time', 'tgl', 'order_date', 'ship_date'])
        if date_cols and num_cols:
            try:
                dc = date_cols[0]
                df_t = df.copy()
                df_t[dc] = pd.to_datetime(df_t[dc], errors='coerce')
                df_t = df_t.dropna(subset=[dc])
                nc = num_cols[0]
                # Yearly
                yearly = df_t.groupby(df_t[dc].dt.year)[nc].agg(['sum', 'count', 'mean']).reset_index()
                yearly.columns = ['year', 'total', 'count', 'avg']
                trend_data = []
                for _, row in yearly.iterrows():
                    trend_data.append({'year': int(row['year']), 'total': round(float(row['total']), 2), 'count': int(row['count']), 'avg': round(float(row['avg']), 2)})
                enriched['yearly_trend'] = trend_data
                # Monthly
                monthly = df_t.groupby(df_t[dc].dt.to_period('M'))[nc].sum()
                peak_month = monthly.idxmax()
                enriched['peak_month'] = str(peak_month)
                enriched['peak_month_value'] = round(float(monthly.max()), 2)
            except Exception as e:
                logger.warning(f"Temporal trend failed: {e}")

        # Categorical breakdown with numeric aggregation
        if cat_cols and num_cols:
            nc = num_cols[0]
            for cc in cat_cols[:2]:
                if df[cc].nunique() <= 20:
                    try:
                        grp = df.groupby(cc)[nc].agg(['sum', 'count', 'mean']).sort_values('sum', ascending=False).head(10)
                        breakdown = []
                        total_sum = df[nc].sum()
                        for idx, row in grp.iterrows():
                            breakdown.append({
                                'label': str(idx),
                                'total': round(float(row['sum']), 2),
                                'count': int(row['count']),
                                'avg': round(float(row['mean']), 2),
                                'pct': round(float(row['sum'] / max(total_sum, 1) * 100), 1)
                            })
                        enriched[f'{cc}_breakdown'] = breakdown
                    except:
                        pass

        # Outlier summary
        outlier_summary = []
        for nc in num_cols[:6]:
            s = df[nc].dropna()
            if len(s) > 10:
                q1, q3 = s.quantile(0.25), s.quantile(0.75)
                iqr = q3 - q1
                extreme = s[(s < q1 - 1.5 * iqr) | (s > q3 + 1.5 * iqr)]
                if len(extreme) > 0:
                    outlier_summary.append({
                        'column': nc, 'count': len(extreme),
                        'pct': round(len(extreme) / len(s) * 100, 1),
                        'max_outlier': round(float(extreme.max()), 2),
                        'min_outlier': round(float(extreme.min()), 2),
                    })
        if outlier_summary:
            enriched['outlier_summary'] = outlier_summary

        return enriched

    # ─── PENJUALAN ───
    def _analyze_sales(self, df: pd.DataFrame) -> Dict[str, Any]:
        insights = {}
        price_cols = _find_cols(df, ['harga', 'price', 'total', 'revenue', 'amount', 'nilai'])
        product_cols = _find_cols(df, ['produk', 'product', 'item', 'nama', 'barang'])
        date_cols = _find_cols(df, ['tanggal', 'date', 'time', 'tgl'])

        if price_cols:
            pc = price_cols[0]
            insights.update(_safe_numeric_stats(df[pc]))
            insights['revenue_column'] = pc

        if price_cols and date_cols:
            try:
                dc = date_cols[0]
                df_work = df.copy()
                df_work[dc] = pd.to_datetime(df_work[dc], errors='coerce')
                df_sorted = df_work.dropna(subset=[dc]).sort_values(dc)
                mid = len(df_sorted) // 2
                if mid > 0:
                    h1 = df_sorted.iloc[:mid][price_cols[0]].sum()
                    h2 = df_sorted.iloc[mid:][price_cols[0]].sum()
                    if h1 != 0:
                        insights['growth_rate'] = round(((h2 - h1) / h1) * 100, 1)
            except Exception as e:
                logger.warning(f"Growth rate calc failed: {e}")

        if product_cols:
            try:
                pc = product_cols[0]
                if price_cols:
                    top = df.groupby(pc)[price_cols[0]].sum().nlargest(5)
                else:
                    top = df[pc].value_counts().head(5)
                insights['top_products'] = {str(k): float(v) for k, v in top.items()}
            except Exception as e:
                logger.warning(f"Top products failed: {e}")

        return {'sales_insights': insights}

    # ─── KEUANGAN ───
    def _analyze_finance(self, df: pd.DataFrame) -> Dict[str, Any]:
        insights = {}
        debit_cols = _find_cols(df, ['debit', 'debet', 'pengeluaran', 'expense', 'biaya', 'cost'])
        credit_cols = _find_cols(df, ['kredit', 'credit', 'pemasukan', 'income', 'pendapatan', 'revenue'])
        amount_cols = _find_cols(df, ['jumlah', 'amount', 'saldo', 'balance', 'nominal', 'nilai'])

        if debit_cols:
            d = pd.to_numeric(df[debit_cols[0]], errors='coerce').dropna()
            insights['total_debit'] = round(float(d.sum()), 2)
        if credit_cols:
            c = pd.to_numeric(df[credit_cols[0]], errors='coerce').dropna()
            insights['total_credit'] = round(float(c.sum()), 2)
        if 'total_debit' in insights and 'total_credit' in insights:
            insights['net_flow'] = round(insights['total_credit'] - insights['total_debit'], 2)

        if amount_cols:
            insights['amount_stats'] = _safe_numeric_stats(df[amount_cols[0]])

        cat_cols = _find_cols(df, ['kategori', 'category', 'jenis', 'type', 'akun', 'account'])
        if cat_cols and amount_cols:
            try:
                top = df.groupby(cat_cols[0])[amount_cols[0]].sum().abs().nlargest(5)
                insights['top_categories'] = {str(k): float(v) for k, v in top.items()}
            except:
                pass

        if not insights:
            insights = self._auto_numeric_summary(df)
        return {'finance_insights': insights}

    # ─── OPERASIONAL ───
    def _analyze_operational(self, df: pd.DataFrame) -> Dict[str, Any]:
        insights = {}
        target_cols = _find_cols(df, ['target', 'goal', 'plan', 'rencana'])
        actual_cols = _find_cols(df, ['aktual', 'actual', 'realisasi', 'result', 'capaian'])
        prod_cols = _find_cols(df, ['produksi', 'production', 'output', 'volume'])

        if target_cols and actual_cols:
            t = pd.to_numeric(df[target_cols[0]], errors='coerce').dropna()
            a = pd.to_numeric(df[actual_cols[0]], errors='coerce').dropna()
            if not t.empty and not a.empty:
                insights['avg_target'] = round(float(t.mean()), 2)
                insights['avg_actual'] = round(float(a.mean()), 2)
                insights['achievement_rate'] = round(float(a.sum() / max(t.sum(), 1) * 100), 1)

        if prod_cols:
            insights['production_stats'] = _safe_numeric_stats(df[prod_cols[0]])

        if not insights:
            insights = self._auto_numeric_summary(df)
        return {'operational_insights': insights}

    # ─── MARKETING ───
    def _analyze_marketing(self, df: pd.DataFrame) -> Dict[str, Any]:
        insights = {}
        click_cols = _find_cols(df, ['klik', 'click', 'views', 'impressions', 'impression', 'view'])
        conv_cols = _find_cols(df, ['konversi', 'conversion', 'converted', 'purchase'])
        budget_cols = _find_cols(df, ['budget', 'biaya', 'cost', 'spend', 'anggaran'])
        channel_cols = _find_cols(df, ['channel', 'platform', 'media', 'sumber', 'source', 'campaign'])

        if click_cols:
            insights['total_clicks'] = int(pd.to_numeric(df[click_cols[0]], errors='coerce').sum())
        if conv_cols:
            insights['total_conversions'] = int(pd.to_numeric(df[conv_cols[0]], errors='coerce').sum())
        if 'total_clicks' in insights and 'total_conversions' in insights and insights['total_clicks'] > 0:
            insights['conversion_rate'] = round(insights['total_conversions'] / insights['total_clicks'] * 100, 2)
        if budget_cols:
            b = pd.to_numeric(df[budget_cols[0]], errors='coerce').dropna()
            insights['total_budget'] = round(float(b.sum()), 2)
            if 'total_conversions' in insights and insights['total_conversions'] > 0:
                insights['cost_per_conversion'] = round(float(b.sum()) / insights['total_conversions'], 2)

        if channel_cols:
            try:
                top = df[channel_cols[0]].value_counts().head(5)
                insights['top_channels'] = {str(k): int(v) for k, v in top.items()}
            except:
                pass

        if not insights:
            insights = self._auto_numeric_summary(df)
        return {'marketing_insights': insights}

    # ─── INVENTORI ───
    def _analyze_inventory(self, df: pd.DataFrame) -> Dict[str, Any]:
        insights = {}
        stock_cols = _find_cols(df, ['stok', 'stock', 'qty', 'kuantitas', 'quantity', 'jumlah'])
        item_cols = _find_cols(df, ['item', 'produk', 'product', 'barang', 'nama', 'sku'])
        price_cols = _find_cols(df, ['harga', 'price', 'cost', 'nilai'])

        if stock_cols:
            s = pd.to_numeric(df[stock_cols[0]], errors='coerce').dropna()
            insights['total_stock'] = int(s.sum())
            insights['stock_stats'] = _safe_numeric_stats(df[stock_cols[0]])
            insights['low_stock_count'] = int((s <= s.quantile(0.1)).sum())
            insights['overstock_count'] = int((s >= s.quantile(0.9)).sum())

        if stock_cols and item_cols:
            try:
                low = df.nsmallest(5, stock_cols[0])[[item_cols[0], stock_cols[0]]]
                insights['lowest_stock_items'] = {str(r[item_cols[0]]): int(r[stock_cols[0]]) for _, r in low.iterrows()}
            except:
                pass

        if stock_cols and price_cols:
            try:
                insights['total_inventory_value'] = round(float((pd.to_numeric(df[stock_cols[0]], errors='coerce') * pd.to_numeric(df[price_cols[0]], errors='coerce')).sum()), 2)
            except:
                pass

        if not insights:
            insights = self._auto_numeric_summary(df)
        return {'inventory_insights': insights}

    # ─── SDM / HR ───
    def _analyze_sdm(self, df: pd.DataFrame) -> Dict[str, Any]:
        insights = {}
        salary_cols = _find_cols(df, ['gaji', 'salary', 'upah', 'wage', 'kompensasi'])
        position_cols = _find_cols(df, ['jabatan', 'position', 'posisi', 'role', 'divisi', 'department', 'departemen'])
        perf_cols = _find_cols(df, ['kinerja', 'performance', 'skor', 'score', 'rating', 'nilai'])
        absent_cols = _find_cols(df, ['absen', 'absent', 'hadir', 'attendance', 'izin', 'cuti', 'leave'])

        if salary_cols:
            insights['salary_stats'] = _safe_numeric_stats(df[salary_cols[0]])
        if salary_cols and position_cols:
            try:
                avg = df.groupby(position_cols[0])[salary_cols[0]].mean().round(0).nlargest(5)
                insights['avg_salary_by_position'] = {str(k): float(v) for k, v in avg.items()}
            except:
                pass
        if perf_cols:
            insights['performance_stats'] = _safe_numeric_stats(df[perf_cols[0]])
        if absent_cols:
            insights['absence_stats'] = _safe_numeric_stats(df[absent_cols[0]])

        insights['total_employees'] = len(df)
        if position_cols:
            insights['position_distribution'] = {str(k): int(v) for k, v in df[position_cols[0]].value_counts().head(8).items()}

        if not insights.get('salary_stats') and not insights.get('performance_stats'):
            insights.update(self._auto_numeric_summary(df))
        return {'sdm_insights': insights}

    # ─── AKADEMIK ───
    def _analyze_akademik(self, df: pd.DataFrame) -> Dict[str, Any]:
        insights = {}
        grade_cols = _find_cols(df, ['nilai', 'grade', 'score', 'skor', 'ips', 'ipk', 'gpa'])
        subject_cols = _find_cols(df, ['mata_pelajaran', 'mapel', 'subject', 'course', 'matkul'])
        student_cols = _find_cols(df, ['siswa', 'student', 'mahasiswa', 'nama', 'nis', 'nim'])

        if grade_cols:
            g = pd.to_numeric(df[grade_cols[0]], errors='coerce').dropna()
            insights['grade_stats'] = _safe_numeric_stats(df[grade_cols[0]])
            if not g.empty:
                threshold = 70 if g.max() > 10 else 2.5
                insights['pass_rate'] = round(float((g >= threshold).sum() / len(g) * 100), 1)
                insights['fail_count'] = int((g < threshold).sum())

        if grade_cols and subject_cols:
            try:
                avg = df.groupby(subject_cols[0])[grade_cols[0]].mean().round(1)
                insights['avg_grade_by_subject'] = {str(k): float(v) for k, v in avg.nlargest(10).items()}
            except:
                pass

        insights['total_students'] = len(df)
        if not insights.get('grade_stats'):
            insights.update(self._auto_numeric_summary(df))
        return {'akademik_insights': insights}

    # ─── KESEHATAN ───
    def _analyze_kesehatan(self, df: pd.DataFrame) -> Dict[str, Any]:
        insights = {}
        diag_cols = _find_cols(df, ['diagnosa', 'diagnosis', 'penyakit', 'disease', 'keluhan'])
        age_cols = _find_cols(df, ['usia', 'age', 'umur'])
        gender_cols = _find_cols(df, ['gender', 'jenis_kelamin', 'kelamin', 'sex'])
        vital_cols = _find_cols(df, ['tekanan', 'pressure', 'suhu', 'temperature', 'berat', 'weight', 'tinggi', 'height', 'bmi'])

        if diag_cols:
            top = df[diag_cols[0]].value_counts().head(8)
            insights['top_diagnoses'] = {str(k): int(v) for k, v in top.items()}
        if age_cols:
            insights['age_stats'] = _safe_numeric_stats(df[age_cols[0]])
        if gender_cols:
            insights['gender_distribution'] = {str(k): int(v) for k, v in df[gender_cols[0]].value_counts().items()}
        for vc in vital_cols[:3]:
            insights[f'{vc}_stats'] = _safe_numeric_stats(df[vc])

        insights['total_patients'] = len(df)
        if not insights.get('top_diagnoses') and not vital_cols:
            insights.update(self._auto_numeric_summary(df))
        return {'kesehatan_insights': insights}

    # ─── LOGISTIK ───
    def _analyze_logistik(self, df: pd.DataFrame) -> Dict[str, Any]:
        insights = {}
        dest_cols = _find_cols(df, ['tujuan', 'destination', 'kota', 'city', 'alamat', 'address'])
        status_cols = _find_cols(df, ['status', 'kondisi', 'state'])
        time_cols = _find_cols(df, ['waktu', 'durasi', 'duration', 'lead_time', 'hari', 'days'])
        weight_cols = _find_cols(df, ['berat', 'weight', 'kg', 'ton', 'volume'])

        if status_cols:
            insights['status_distribution'] = {str(k): int(v) for k, v in df[status_cols[0]].value_counts().items()}
            on_time_keys = ['delivered', 'selesai', 'sukses', 'success', 'on time', 'terkirim']
            total = len(df)
            on_time = df[status_cols[0]].str.lower().isin(on_time_keys).sum()
            if total > 0:
                insights['on_time_rate'] = round(float(on_time / total * 100), 1)

        if time_cols:
            insights['delivery_time_stats'] = _safe_numeric_stats(df[time_cols[0]])
        if dest_cols:
            insights['top_destinations'] = {str(k): int(v) for k, v in df[dest_cols[0]].value_counts().head(5).items()}
        if weight_cols:
            insights['weight_stats'] = _safe_numeric_stats(df[weight_cols[0]])

        insights['total_shipments'] = len(df)
        if not insights.get('delivery_time_stats') and not insights.get('status_distribution'):
            insights.update(self._auto_numeric_summary(df))
        return {'logistik_insights': insights}

    # ─── SURVEY ───
    def _analyze_survey(self, df: pd.DataFrame) -> Dict[str, Any]:
        insights = {}
        rating_cols = _find_cols(df, ['rating', 'skor', 'score', 'penilaian', 'nilai', 'satisfaction'])
        question_cols = _find_cols(df, ['pertanyaan', 'question', 'aspek', 'kategori', 'dimensi'])

        if rating_cols:
            r = pd.to_numeric(df[rating_cols[0]], errors='coerce').dropna()
            insights['rating_stats'] = _safe_numeric_stats(df[rating_cols[0]])
            if not r.empty:
                scale_max = 5 if r.max() <= 5 else (10 if r.max() <= 10 else 100)
                insights['rating_scale'] = f"1-{scale_max}"
                insights['rating_distribution'] = {str(k): int(v) for k, v in r.value_counts().sort_index().items()}
                # NPS approximation (for 1-10 scale)
                if scale_max == 10:
                    promoters = (r >= 9).sum()
                    detractors = (r <= 6).sum()
                    insights['nps_score'] = round(float((promoters - detractors) / len(r) * 100), 1)

        if rating_cols and question_cols:
            try:
                avg = df.groupby(question_cols[0])[rating_cols[0]].mean().round(2)
                insights['avg_rating_by_category'] = {str(k): float(v) for k, v in avg.items()}
            except:
                pass

        insights['total_respondents'] = len(df)
        if not insights.get('rating_stats'):
            insights.update(self._auto_numeric_summary(df))
        return {'survey_insights': insights}

    # ─── GENERIC (fallback) ───
    def _analyze_generic(self, df: pd.DataFrame) -> Dict[str, Any]:
        return {'generic_insights': self._auto_numeric_summary(df)}

    def _auto_numeric_summary(self, df: pd.DataFrame) -> Dict[str, Any]:
        """Auto-detect dan summarize semua kolom numerik"""
        result = {}
        num_cols = df.select_dtypes(include=[np.number]).columns.tolist()
        for col in num_cols[:6]:
            result[f'{col}_summary'] = _safe_numeric_stats(df[col])

        # Top correlations
        if len(num_cols) >= 2:
            try:
                corr = df[num_cols].corr()
                pairs = []
                for i in range(len(num_cols)):
                    for j in range(i + 1, len(num_cols)):
                        pairs.append((num_cols[i], num_cols[j], abs(corr.iloc[i, j])))
                pairs.sort(key=lambda x: x[2], reverse=True)
                result['top_correlations'] = [
                    {'col1': p[0], 'col2': p[1], 'correlation': round(p[2], 3)}
                    for p in pairs[:3] if not np.isnan(p[2])
                ]
            except:
                pass

        # Categorical top values
        cat_cols = df.select_dtypes(include=['object']).columns.tolist()
        for col in cat_cols[:3]:
            result[f'{col}_top_values'] = {str(k): int(v) for k, v in df[col].value_counts().head(5).items()}

        return result


if __name__ == "__main__":
    analyzer = DataAnalyzer()
    df = pd.DataFrame({'A': [1, 2, 3], 'B': [4, 5, 6]})
    result = analyzer.run(df, 'umum')
    print(result)
