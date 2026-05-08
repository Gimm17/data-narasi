"""
Chart Generator Module v2
Adaptive chart generation — 8-12 charts based on data characteristics
Inspired by Claude's analysis output quality
"""

import pandas as pd
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import matplotlib.ticker as mticker
import numpy as np
import os
import base64
from pathlib import Path
import logging
from typing import Dict, List, Any, Optional
import seaborn as sns

logger = logging.getLogger(__name__)

# Premium color palette
COLORS = ['#1B4F72', '#2E86C1', '#F39C12', '#27AE60', '#E74C3C', '#8E44AD', '#16A085', '#D35400']
SINGLE_COLOR = '#1B4F72'
ACCENT_COLOR = '#E74C3C'
BG_COLOR = '#FAFBFC'


class ChartGenerator:
    """Adaptive chart generator — picks the best charts for each dataset"""

    def __init__(self):
        plt.style.use('seaborn-v0_8-whitegrid')
        matplotlib.rcParams.update({
            'figure.figsize': (12, 6),
            'font.size': 11,
            'axes.titlesize': 14,
            'axes.titleweight': 'bold',
            'axes.labelsize': 11,
            'figure.facecolor': BG_COLOR,
            'axes.facecolor': '#FFFFFF',
            'savefig.facecolor': BG_COLOR,
        })

    def run(self, df: pd.DataFrame, stats: Dict[str, Any], report_id: int) -> List[Dict[str, str]]:
        logger.info("Starting adaptive chart generation...")
        chart_paths = []
        chart_folder = Path(__file__).parent / 'storage' / 'charts' / str(report_id)
        chart_folder.mkdir(parents=True, exist_ok=True)

        # Detect column types
        date_cols = [c for c in df.columns if self._is_date_column(df, c)]
        num_cols = df.select_dtypes(include=[np.number]).columns.tolist()
        _id_suffixes = ('_id', '_code', '_no', 'id', 'code')
        all_cat = [c for c in df.select_dtypes(include=['object']).columns if df[c].nunique() <= 20]
        # Prioritize name/label columns over ID/code columns for chart readability
        cat_cols = sorted(all_cat, key=lambda c: any(c.lower().endswith(s) for s in _id_suffixes))
        cat_small = [c for c in cat_cols if df[c].nunique() <= 8]

        chart_idx = 0

        # 1. Trend Line + Area Fill (date + numeric)
        if date_cols and num_cols:
            p = self._chart_trend_line(df, date_cols[0], num_cols[0], chart_folder, chart_idx)
            if p: chart_paths.append(p); chart_idx += 1

        # 2. Bar: Top N by category (cat + numeric aggregation)
        if cat_cols and num_cols:
            p = self._chart_category_bar(df, cat_cols[0], num_cols[0], chart_folder, chart_idx)
            if p: chart_paths.append(p); chart_idx += 1

        # 3. Horizontal Bar: second categorical or subcategory
        if len(cat_cols) >= 2 and num_cols:
            p = self._chart_horizontal_bar(df, cat_cols[1], num_cols[0], chart_folder, chart_idx)
            if p: chart_paths.append(p); chart_idx += 1

        # 4. Pie/Donut for small categorical
        if cat_small:
            p = self._chart_donut(df, cat_small[0], num_cols[0] if num_cols else None, chart_folder, chart_idx)
            if p: chart_paths.append(p); chart_idx += 1

        # 5. Heatmap: 2 categoricals × 1 numeric
        if len(cat_cols) >= 2 and num_cols:
            p = self._chart_heatmap(df, cat_cols[0], cat_cols[1], num_cols[0], chart_folder, chart_idx)
            if p: chart_paths.append(p); chart_idx += 1

        # 6. Boxplot per category
        if cat_small and num_cols:
            p = self._chart_boxplot(df, cat_small[0], num_cols[0], chart_folder, chart_idx)
            if p: chart_paths.append(p); chart_idx += 1

        # 7. Histogram + KDE
        if num_cols:
            p = self._chart_histogram(df, num_cols[0], chart_folder, chart_idx)
            if p: chart_paths.append(p); chart_idx += 1

        # 8. Scatter correlation (top 2 correlated numeric cols)
        if len(num_cols) >= 2:
            p = self._chart_scatter(df, num_cols, cat_cols, chart_folder, chart_idx)
            if p: chart_paths.append(p); chart_idx += 1

        logger.info(f"✅ Generated {len(chart_paths)} charts")

        # Encode to base64
        results = []
        for p in chart_paths:
            p_path = Path(p)
            relative = f"charts/{report_id}/{p_path.name}"
            try:
                with open(p, 'rb') as f:
                    b64 = base64.b64encode(f.read()).decode('utf-8')
                results.append({'path': relative, 'data': b64, 'filename': p_path.name})
            except Exception as e:
                logger.warning(f"Could not read chart {p}: {e}")
                results.append({'path': relative, 'data': None, 'filename': p_path.name})
        return results

    def _is_date_column(self, df: pd.DataFrame, col: str) -> bool:
        try:
            converted = pd.to_datetime(df[col], errors='coerce')
            return converted.notna().sum() > len(df) * 0.8
        except:
            return False

    def _fmt_number(self, n: float) -> str:
        if abs(n) >= 1_000_000: return f"${n/1_000_000:.1f}M" if n > 0 else f"{n/1_000_000:.1f}M"
        if abs(n) >= 1_000: return f"${n/1_000:.0f}K" if n > 0 else f"{n/1_000:.0f}K"
        return f"{n:,.0f}"

    def _save(self, fig, folder: Path, idx: int, name: str) -> str:
        path = folder / f"{idx+1}_{name}.png"
        fig.savefig(path, dpi=120, bbox_inches='tight')
        plt.close(fig)
        logger.info(f"Chart saved: {path.name}")
        return str(path)

    # ── Chart 1: Trend Line + Area ──
    def _chart_trend_line(self, df, date_col, val_col, folder, idx):
        try:
            df_w = df.copy()
            df_w[date_col] = pd.to_datetime(df_w[date_col], errors='coerce')
            df_w = df_w.dropna(subset=[date_col]).sort_values(date_col)
            monthly = df_w.groupby(df_w[date_col].dt.to_period('M'))[val_col].sum()
            if len(monthly) < 3: return None

            fig, ax = plt.subplots(figsize=(14, 5))
            x = range(len(monthly))
            ax.fill_between(x, monthly.values, alpha=0.15, color=SINGLE_COLOR)
            ax.plot(x, monthly.values, linewidth=2, color=SINGLE_COLOR, marker='o', markersize=3)

            # Peak annotation
            peak_idx = monthly.values.argmax()
            ax.scatter([peak_idx], [monthly.values[peak_idx]], color=ACCENT_COLOR, s=80, zorder=5)
            ax.annotate(f'Peak: {self._fmt_number(monthly.values[peak_idx])}',
                       xy=(peak_idx, monthly.values[peak_idx]),
                       xytext=(10, 15), textcoords='offset points',
                       fontsize=9, color=ACCENT_COLOR, fontweight='bold')

            labels = [str(p) for p in monthly.index]
            step = max(1, len(labels) // 12)
            ax.set_xticks(x[::step])
            ax.set_xticklabels(labels[::step], rotation=45, ha='right', fontsize=8)
            ax.set_title(f'📈 Tren {val_col} Bulanan')
            ax.set_ylabel(val_col)
            ax.yaxis.set_major_formatter(mticker.FuncFormatter(lambda x, _: self._fmt_number(x)))
            return self._save(fig, folder, idx, 'tren_bulanan')
        except Exception as e:
            logger.warning(f"Trend line failed: {e}")
            return None

    # ── Chart 2: Category Bar ──
    def _chart_category_bar(self, df, cat_col, val_col, folder, idx):
        try:
            grp = df.groupby(cat_col)[val_col].sum().sort_values(ascending=False).head(10)
            if grp.empty: return None

            fig, ax = plt.subplots(figsize=(10, 6))
            bars = ax.bar(range(len(grp)), grp.values, color=COLORS[:len(grp)])
            ax.set_xticks(range(len(grp)))
            ax.set_xticklabels(grp.index, rotation=45, ha='right')
            for bar, val in zip(bars, grp.values):
                ax.text(bar.get_x() + bar.get_width()/2, bar.get_height(),
                       self._fmt_number(val), ha='center', va='bottom', fontsize=9, fontweight='bold')
            ax.set_title(f'📊 {val_col} per {cat_col}')
            ax.set_ylabel(val_col)
            ax.yaxis.set_major_formatter(mticker.FuncFormatter(lambda x, _: self._fmt_number(x)))
            return self._save(fig, folder, idx, 'bar_kategori')
        except Exception as e:
            logger.warning(f"Category bar failed: {e}")
            return None

    # ── Chart 3: Horizontal Bar ──
    def _chart_horizontal_bar(self, df, cat_col, val_col, folder, idx):
        try:
            grp = df.groupby(cat_col)[val_col].sum().sort_values(ascending=True).tail(10)
            if grp.empty: return None

            fig, ax = plt.subplots(figsize=(10, 6))
            bars = ax.barh(range(len(grp)), grp.values, color=COLORS[:len(grp)])
            ax.set_yticks(range(len(grp)))
            ax.set_yticklabels(grp.index)
            for bar, val in zip(bars, grp.values):
                ax.text(bar.get_width(), bar.get_y() + bar.get_height()/2,
                       f' {self._fmt_number(val)}', va='center', fontsize=9, fontweight='bold')
            ax.set_title(f'📊 Top {cat_col} by {val_col}')
            ax.set_xlabel(val_col)
            return self._save(fig, folder, idx, 'bar_horizontal')
        except Exception as e:
            logger.warning(f"Horizontal bar failed: {e}")
            return None

    # ── Chart 4: Donut / Pie ──
    def _chart_donut(self, df, cat_col, val_col, folder, idx):
        try:
            if val_col:
                data = df.groupby(cat_col)[val_col].sum().sort_values(ascending=False).head(8)
            else:
                data = df[cat_col].value_counts().head(8)
            if data.empty or len(data) < 2: return None

            fig, ax = plt.subplots(figsize=(8, 8))
            wedges, texts, autotexts = ax.pie(
                data.values, labels=data.index, autopct='%1.1f%%',
                colors=COLORS[:len(data)], startangle=90,
                pctdistance=0.8, wedgeprops=dict(width=0.4, edgecolor='white'))
            for t in autotexts: t.set_fontsize(9); t.set_fontweight('bold')
            ax.set_title(f'🍩 Proporsi {cat_col}')
            return self._save(fig, folder, idx, 'donut_proporsi')
        except Exception as e:
            logger.warning(f"Donut chart failed: {e}")
            return None

    # ── Chart 5: Heatmap ──
    def _chart_heatmap(self, df, cat1, cat2, val_col, folder, idx):
        try:
            if df[cat1].nunique() > 10 or df[cat2].nunique() > 10: return None
            pivot = df.pivot_table(values=val_col, index=cat1, columns=cat2, aggfunc='sum', fill_value=0)
            if pivot.empty: return None

            fig, ax = plt.subplots(figsize=(10, 6))
            sns.heatmap(pivot, annot=True, fmt=',.0f', cmap='Blues', linewidths=0.5,
                       ax=ax, cbar_kws={'label': val_col})
            ax.set_title(f'🔥 Heatmap: {cat1} × {cat2}')
            return self._save(fig, folder, idx, 'heatmap')
        except Exception as e:
            logger.warning(f"Heatmap failed: {e}")
            return None

    # ── Chart 6: Boxplot per Category ──
    def _chart_boxplot(self, df, cat_col, val_col, folder, idx):
        try:
            cats = df[cat_col].value_counts().head(6).index.tolist()
            df_f = df[df[cat_col].isin(cats)]
            if df_f.empty: return None

            fig, ax = plt.subplots(figsize=(10, 6))
            box_data = [df_f[df_f[cat_col] == c][val_col].dropna().values for c in cats]
            bp = ax.boxplot(box_data, labels=cats, patch_artist=True, showfliers=True)
            for patch, color in zip(bp['boxes'], COLORS): patch.set_facecolor(color); patch.set_alpha(0.7)
            ax.set_title(f'📦 Distribusi {val_col} per {cat_col}')
            ax.set_ylabel(val_col)
            ax.tick_params(axis='x', rotation=30)
            return self._save(fig, folder, idx, 'boxplot')
        except Exception as e:
            logger.warning(f"Boxplot failed: {e}")
            return None

    # ── Chart 7: Histogram + KDE ──
    def _chart_histogram(self, df, val_col, folder, idx):
        try:
            data = df[val_col].dropna()
            if data.empty: return None

            fig, ax = plt.subplots(figsize=(10, 6))
            ax.hist(data, bins=40, color=SINGLE_COLOR, alpha=0.7, edgecolor='white', density=False)

            mean_v, median_v = data.mean(), data.median()
            ax.axvline(median_v, color=ACCENT_COLOR, linestyle='--', lw=2, label=f'Median: {self._fmt_number(median_v)}')
            ax.axvline(mean_v, color='#F39C12', linestyle='--', lw=2, label=f'Mean: {self._fmt_number(mean_v)}')
            ax.legend(fontsize=10)
            ax.set_title(f'📊 Distribusi {val_col}')
            ax.set_xlabel(val_col)
            ax.set_ylabel('Frekuensi')
            return self._save(fig, folder, idx, 'distribusi')
        except Exception as e:
            logger.warning(f"Histogram failed: {e}")
            return None

    # ── Chart 8: Scatter Correlation ──
    def _chart_scatter(self, df, num_cols, cat_cols, folder, idx):
        try:
            if len(num_cols) < 2: return None
            corr = df[num_cols].corr()
            pairs = []
            for i in range(len(num_cols)):
                for j in range(i+1, len(num_cols)):
                    v = corr.iloc[i, j]
                    if not np.isnan(v): pairs.append((num_cols[i], num_cols[j], abs(v)))
            if not pairs: return None
            pairs.sort(key=lambda x: x[2], reverse=True)
            c1, c2, r = pairs[0]

            fig, ax = plt.subplots(figsize=(10, 6))
            hue_col = cat_cols[0] if cat_cols and df[cat_cols[0]].nunique() <= 6 else None
            if hue_col:
                for i, cat in enumerate(df[hue_col].unique()[:6]):
                    mask = df[hue_col] == cat
                    ax.scatter(df.loc[mask, c1], df.loc[mask, c2], alpha=0.5, s=20, color=COLORS[i], label=str(cat))
                ax.legend(fontsize=8, loc='upper right')
            else:
                ax.scatter(df[c1], df[c2], alpha=0.4, s=15, color=SINGLE_COLOR)
            ax.set_xlabel(c1)
            ax.set_ylabel(c2)
            ax.set_title(f'🔗 Korelasi: {c1} vs {c2} (r={r:.2f})')
            return self._save(fig, folder, idx, 'scatter_korelasi')
        except Exception as e:
            logger.warning(f"Scatter failed: {e}")
            return None


if __name__ == "__main__":
    gen = ChartGenerator()
    df = pd.DataFrame({
        'tanggal': pd.date_range('2025-01-01', periods=100),
        'sales': np.random.randint(100, 5000, 100),
        'category': np.random.choice(['A', 'B', 'C'], 100)
    })
    paths = gen.run(df, {}, 999)
    print(f"Charts: {len(paths)}")
