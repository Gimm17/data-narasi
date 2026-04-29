"""
Chart Generator Module
Membuat chart visualisasi dengan Matplotlib
"""

import pandas as pd
import matplotlib
matplotlib.use('Agg')  # Use non-interactive backend
import matplotlib.pyplot as plt
import numpy as np
from pathlib import Path
import logging
from typing import Dict, List, Any

logger = logging.getLogger(__name__)


class ChartGenerator:
    """
    Generate chart visualisasi dari data
    """

    def __init__(self):
        """Setup matplotlib style"""
        plt.style.use('seaborn-v0_8-darkgrid')
        matplotlib.rcParams['figure.figsize'] = (10, 6)
        matplotlib.rcParams['font.size'] = 10

    def run(self, df: pd.DataFrame, stats: Dict[str, Any], report_id: int) -> List[str]:
        """
        Generate charts dari DataFrame

        Args:
            df: DataFrame yang sudah dibersihkan
            stats: Hasil analisis dari DataAnalyzer
            report_id: ID report untuk penamaan folder

        Returns:
            List of chart file paths
        """
        logger.info("Starting chart generation...")

        chart_paths = []

        try:
            # Buat folder untuk charts
            chart_folder = Path(f"storage/charts/{report_id}")
            chart_folder.mkdir(parents=True, exist_ok=True)

            # Chart 1: Line chart untuk tren (jika ada kolom tanggal)
            date_cols = [col for col in df.columns if self._is_date_column(df, col)]
            numeric_cols = df.select_dtypes(include=[np.number]).columns.tolist()

            if date_cols and numeric_cols:
                line_chart_path = self._create_line_chart(
                    df, date_cols[0], numeric_cols[0], chart_folder
                )
                chart_paths.append(line_chart_path)

            # Chart 2: Bar chart untuk kategori (jika ada kolom string dengan unique value ≤ 10)
            for col in df.columns:
                if df[col].dtype == 'object' and df[col].nunique() <= 10:
                    bar_chart_path = self._create_bar_chart(
                        df, col, chart_folder
                    )
                    chart_paths.append(bar_chart_path)
                    break  # Hanya buat 1 bar chart

            # Chart 3: Histogram untuk distribusi (jika ada numeric column)
            if numeric_cols:
                hist_path = self._create_histogram(
                    df, numeric_cols[0], chart_folder
                )
                chart_paths.append(hist_path)

            logger.info(f"✅ Generated {len(chart_paths)} charts")

            return chart_paths

        except Exception as e:
            logger.error(f"Error generating charts: {str(e)}")
            return []

    def _is_date_column(self, df: pd.DataFrame, col: str) -> bool:
        """Cek apakah kolom adalah tanggal (tanpa mengubah data asli)"""
        try:
            converted = pd.to_datetime(df[col], errors='coerce')
            return converted.notna().sum() > len(df) * 0.8  # 80% values berhasil convert
        except:
            return False

    def _create_line_chart(self, df: pd.DataFrame, date_col: str, value_col: str, output_folder: Path) -> str:
        """Buat line chart untuk tren"""
        try:
            df_work = df.copy()
            df_work[date_col] = pd.to_datetime(df_work[date_col], errors='coerce')
            df_sorted = df_work.dropna(subset=[date_col]).sort_values(date_col)

            fig, ax = plt.subplots(figsize=(12, 6))

            ax.plot(df_sorted[date_col], df_sorted[value_col], linewidth=2, color='#0F6E56')
            ax.set_xlabel('Tanggal')
            ax.set_ylabel(value_col)
            ax.set_title(f'Tren {value_col} dari Waktu ke Waktu')
            ax.grid(True, alpha=0.3)

            # Format tanggal di x-axis
            fig.autofmt_xdate()

            chart_path = output_folder / 'tren_waktu.png'
            plt.tight_layout()
            plt.savefig(chart_path, dpi=100, bbox_inches='tight')
            plt.close()

            logger.info(f"Line chart saved: {chart_path}")
            return str(chart_path)

        except Exception as e:
            logger.error(f"Error creating line chart: {e}")
            return ""

    def _create_bar_chart(self, df: pd.DataFrame, category_col: str, output_folder: Path) -> str:
        """Buat bar chart untuk kategori"""
        try:
            # Count atau sum depending on data type
            if df[category_col].nunique() <= 20:
                value_counts = df[category_col].value_counts().head(10)
            else:
                value_counts = df[category_col].value_counts().head(10)

            fig, ax = plt.subplots(figsize=(10, 6))

            bars = ax.bar(value_counts.index, value_counts.values, color='#0F6E56')
            ax.set_xlabel(category_col)
            ax.set_ylabel('Jumlah')
            ax.set_title(f'Distribusi {category_col}')
            ax.tick_params(axis='x', rotation=45)

            # Add value labels on bars
            for bar in bars:
                height = bar.get_height()
                ax.text(bar.get_x() + bar.get_width()/2., height,
                       f'{int(height)}',
                       ha='center', va='bottom', fontsize=8)

            plt.tight_layout()

            chart_path = output_folder / 'distribusi_kategori.png'
            plt.savefig(chart_path, dpi=100, bbox_inches='tight')
            plt.close()

            logger.info(f"Bar chart saved: {chart_path}")
            return str(chart_path)

        except Exception as e:
            logger.error(f"Error creating bar chart: {e}")
            return ""

    def _create_histogram(self, df: pd.DataFrame, numeric_col: str, output_folder: Path) -> str:
        """Buat histogram distribusi"""
        try:
            fig, ax = plt.subplots(figsize=(10, 6))

            ax.hist(df[numeric_col].dropna(), bins=30, color='#0F6E56', alpha=0.7, edgecolor='black')
            ax.set_xlabel(numeric_col)
            ax.set_ylabel('Frekuensi')
            ax.set_title(f'Distribusi {numeric_col}')
            ax.grid(True, alpha=0.3)

            # Add mean line
            mean_val = df[numeric_col].mean()
            ax.axvline(mean_val, color='red', linestyle='--', linewidth=2, label=f'Mean: {mean_val:.2f}')
            ax.legend()

            plt.tight_layout()

            chart_path = output_folder / 'distribusi_numeric.png'
            plt.savefig(chart_path, dpi=100, bbox_inches='tight')
            plt.close()

            logger.info(f"Histogram saved: {chart_path}")
            return str(chart_path)

        except Exception as e:
            logger.error(f"Error creating histogram: {e}")
            return ""


if __name__ == "__main__":
    # Test
    chart_gen = ChartGenerator()
    df = pd.DataFrame({
        'tanggal': pd.date_range('2025-01-01', periods=10),
        'penjualan': np.random.randint(100, 500, 10),
        'kategori': np.random.choice(['A', 'B', 'C'], 10)
    })
    paths = chart_gen.run(df, {}, 123)
    print(f"Charts generated: {paths}")
