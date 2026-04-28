"""
Data Analyzer Module
Menghitung statistik dan tren dari dataset
"""

import pandas as pd
import numpy as np
from typing import Dict, Any
import logging

logger = logging.getLogger(__name__)


class DataAnalyzer:
    """
    Analisis statistik dengan Pandas
    """

    def run(self, df: pd.DataFrame, analysis_type: str) -> Dict[str, Any]:
        """
        Jalankan analisis statistik

        Args:
            df: DataFrame yang sudah dibersihkan
            analysis_type: Tipe analisis (penjualan, keuangan, dll)

        Returns:
            Dict dengan statistik lengkap
        """
        logger.info(f"Starting statistical analysis (type: {analysis_type})")

        stats = {
            'basic_stats': self._basic_stats(df),
            'column_info': self._column_info(df),
            'analysis_type': analysis_type,
            'insights': []
        }

        # Analisis spesifik berdasarkan tipe
        if analysis_type == 'penjualan':
            stats.update(self._analyze_sales(df))
        elif analysis_type == 'keuangan':
            stats.update(self._analyze_finance(df))
        elif analysis_type == 'operasional':
            stats.update(self._analyze_operational(df))
        elif analysis_type == 'marketing':
            stats.update(self._analyze_marketing(df))
        elif analysis_type == 'inventori':
            stats.update(self._analyze_inventory(df))
        else:  # umum
            stats.update(self._analyze_generic(df))

        logger.info("✅ Statistical analysis complete!")

        return stats

    def _basic_stats(self, df: pd.DataFrame) -> Dict[str, Any]:
        """Statistik dasar dataset"""
        return {
            'total_rows': len(df),
            'total_columns': len(df.columns),
            'numeric_columns': len(df.select_dtypes(include=[np.number]).columns),
            'string_columns': len(df.select_dtypes(include=['object']).columns),
            'memory_usage_mb': df.memory_usage(deep=True).sum() / (1024 * 1024)
        }

    def _column_info(self, df: pd.DataFrame) -> Dict[str, Any]:
        """Informasi per kolom"""
        column_info = {}

        for col in df.columns:
            col_info = {
                'name': col,
                'dtype': str(df[col].dtype),
                'null_count': int(df[col].isna().sum()),
                'null_percentage': float(df[col].isna().sum() / len(df) * 100),
                'unique_count': int(df[col].nunique())
            }

            # Numeric stats
            if pd.api.types.is_numeric_dtype(df[col]):
                col_info['is_numeric'] = True
                col_info['min'] = float(df[col].min()) if not df[col].empty else None
                col_info['max'] = float(df[col].max()) if not df[col].empty else None
                col_info['mean'] = float(df[col].mean()) if not df[col].empty else None
                col_info['median'] = float(df[col].median()) if not df[col].empty else None
                col_info['std'] = float(df[col].std()) if not df[col].empty else None
            else:
                col_info['is_numeric'] = False
                # Sample values untuk string
                sample_values = df[col].dropna().head(5).tolist()
                col_info['sample_values'] = sample_values

            column_info[col] = col_info

        return column_info

    def _analyze_sales(self, df: pd.DataFrame) -> Dict[str, Any]:
        """Analisis khusus data penjualan"""
        sales_insights = {}

        # Cari kolom yang mungkin berisi harga/total
        price_cols = [col for col in df.columns if 'harga' in col.lower() or 'price' in col.lower() or 'total' in col.lower()]
        date_cols = [col for col in df.columns if 'tanggal' in col.lower() or 'date' in col.lower() or 'time' in col.lower()]
        product_cols = [col for col in df.columns if 'produk' in col.lower() or 'product' in col.lower() or 'item' in col.lower()]

        # Total revenue (jika ada kolom harga)
        if price_cols:
            price_col = price_cols[0]
            try:
                total_revenue = df[price_col].sum()
                sales_insights['total_revenue'] = float(total_revenue)
                sales_insights['revenue_column'] = price_col
            except:
                pass

        # Growth rate (jika ada kolom tanggal)
        if date_cols:
            try:
                date_col = date_cols[0]
                df[date_col] = pd.to_datetime(df[date_col], errors='coerce')
                df_sorted = df.sort_values(date_col)

                mid_point = len(df_sorted) // 2
                first_half = df_sorted.iloc[:mid_point]
                second_half = df_sorted.iloc[mid_point:]

                if price_cols and len(first_half) > 0 and len(second_half) > 0:
                    price_col = price_cols[0]
                    first_half_sum = first_half[price_col].sum()
                    second_half_sum = second_half[price_col].sum()

                    if first_half_sum != 0:
                        growth_rate = ((second_half_sum - first_half_sum) / first_half_sum) * 100
                        sales_insights['growth_rate'] = float(growth_rate)
            except Exception as e:
                logger.warning(f"Could not calculate growth rate: {e}")

        # Top products (jika ada kolom produk)
        if product_cols:
            try:
                product_col = product_cols[0]
                if price_cols:
                    # Group by product dan sum harga
                    top_products = df.groupby(product_col)[price_cols[0]].sum().nlargest(5)
                    sales_insights['top_products'] = {
                        name: float(value) for name, value in top_products.items()
                    }
                else:
                    # Count frequency
                    top_products = df[product_col].value_counts().head(5)
                    sales_insights['top_products'] = top_products.to_dict()
            except Exception as e:
                logger.warning(f"Could not calculate top products: {e}")

        return {'sales_insights': sales_insights}

    def _analyze_finance(self, df: pd.DataFrame) -> Dict[str, Any]:
        """Analisis khusus data keuangan"""
        finance_insights = {}

        # Cari kolom yang mungkin berisi nilai keuangan
        amount_cols = [col for col in df.columns if any(keyword in col.lower() for keyword in ['jumlah', 'amount', 'saldo', 'balance', 'kredit', 'debit'])]

        return {'finance_insights': finance_insights}

    def _analyze_operational(self, df: pd.DataFrame) -> Dict[str, Any]:
        """Analisis khusus data operasional"""
        return {'operational_insights': {}}

    def _analyze_marketing(self, df: pd.DataFrame) -> Dict[str, Any]:
        """Analisis khusus data marketing"""
        return {'marketing_insights': {}}

    def _analyze_inventory(self, df: pd.DataFrame) -> Dict[str, Any]:
        """Analisis khusus data inventori"""
        return {'inventory_insights': {}}

    def _analyze_generic(self, df: pd.DataFrame) -> Dict[str, Any]:
        """Analisis generik untuk tipe umum"""
        return {'generic_insights': {}}


if __name__ == "__main__":
    # Test
    analyzer = DataAnalyzer()
    df = pd.DataFrame({'A': [1, 2, 3], 'B': [4, 5, 6]})
    result = analyzer.run(df, 'umum')
    print(result)
