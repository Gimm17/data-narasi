"""
Data Cleaner Module
Membersihkan data CSV/Excel dengan Pandas pipeline
"""

import pandas as pd
import numpy as np
import logging
from pathlib import Path
from typing import Dict, Any
import chardet

logger = logging.getLogger(__name__)


class DataCleaner:
    """
    Data Cleansing Pipeline dengan 8 langkah sesuai SKILL.md
    """

    def run(self, file_path: str) -> Dict[str, Any]:
        """
        Jalankan pipeline cleansing

        Args:
            file_path: Path ke file CSV/Excel

        Returns:
            Dict dengan 'df' (DataFrame bersih) dan 'cleaning_log' (log perubahan)
        """
        logger.info(f"Starting data cleansing for: {file_path}")

        # Normalize path untuk handle Windows backslash + forward slash campuran
        file_path = str(Path(file_path).resolve())
        logger.info(f"Resolved path: {file_path}")

        if not Path(file_path).exists():
            raise FileNotFoundError(f"File not found: {file_path}")

        cleaning_log = {
            'original_rows': 0,
            'final_rows': 0,
            'duplicates_removed': 0,
            'nulls_filled': {},
            'type_conversions': [],
            'anomalies_flagged': []
        }

        try:
            # Step 1: Deteksi encoding
            logger.info("Step 1: Detecting encoding...")
            with open(file_path, 'rb') as f:
                result = chardet.detect(f.read())
                encoding = result['encoding']
                confidence = result['confidence']

            logger.info(f"Detected encoding: {encoding} (confidence: {confidence:.2f})")

            # Step 2: Baca file berdasarkan ekstensi
            logger.info("Step 2: Reading file...")
            file_ext = Path(file_path).suffix.lower()

            if file_ext in ['.xlsx', '.xls']:
                df = pd.read_excel(file_path)
            else:
                df = pd.read_csv(file_path, encoding=encoding)

            cleaning_log['original_rows'] = len(df)
            logger.info(f"Original rows: {len(df)}")

            # Step 3: Strip whitespace semua string columns
            logger.info("Step 3: Stripping whitespace...")
            string_cols = df.select_dtypes(include=['object']).columns
            for col in string_cols:
                df[col] = df[col].astype(str).str.strip()

            # Step 4: Hapus baris yang SEMUA kolomnya kosong
            logger.info("Step 4: Removing empty rows...")
            before_count = len(df)
            df = df.dropna(how='all')
            empty_removed = before_count - len(df)
            logger.info(f"Removed {empty_removed} completely empty rows")

            # Step 5: Hapus duplikat exact
            logger.info("Step 5: Removing exact duplicates...")
            before_count = len(df)
            df = df.drop_duplicates()
            duplicates_removed = before_count - len(df)
            cleaning_log['duplicates_removed'] = duplicates_removed
            logger.info(f"Removed {duplicates_removed} duplicate rows")

            # Step 6: Deteksi dan konversi tipe data otomatis
            logger.info("Step 6: Converting data types...")
            for col in df.columns:
                # Skip jika sudah numeric
                if pd.api.types.is_numeric_dtype(df[col]):
                    continue

                # Coba convert ke numeric
                try:
                    # Remove non-numeric characters (kecuali titik dan minus)
                    cleaned_series = df[col].astype(str).str.replace(r'[^\d.\-]', '', regex=True)

                    # Coba convert
                    converted = pd.to_numeric(cleaned_series, errors='coerce')

                    # Jika lebih dari 50% berhasil convert, gunakan tipe numeric
                    non_null_count = converted.notna().sum()
                    if non_null_count > len(df) * 0.5:
                        df[col] = converted
                        cleaning_log['type_conversions'].append({
                            'column': col,
                            'to_type': 'numeric',
                            'success_rate': f"{(non_null_count/len(df)*100):.1f}%"
                        })
                        logger.info(f"Converted {col} to numeric ({non_null_count}/{len(df)} values)")

                except Exception as e:
                    logger.warning(f"Could not convert {col} to numeric: {e}")

            # Step 7: Handle nilai negatif di kolom numeric
            logger.info("Step 7: Flagging negative values...")
            numeric_cols = df.select_dtypes(include=[np.number]).columns
            for col in numeric_cols:
                negative_count = (df[col] < 0).sum()
                if negative_count > 0:
                    anomalies = {
                        'column': col,
                        'issue': 'negative_values',
                        'count': int(negative_count),
                        'percentage': f"{(negative_count/len(df)*100):.2f}%"
                    }
                    cleaning_log['anomalies_flagged'].append(anomalies)
                    logger.info(f"Flagged {negative_count} negative values in {col}")

            # Step 8: Isi null values
            logger.info("Step 8: Filling null values...")
            for col in df.columns:
                null_count = df[col].isna().sum()

                if null_count == 0:
                    continue

                if pd.api.types.is_numeric_dtype(df[col]):
                    # Numeric → isi dengan median
                    median_val = df[col].median()
                    df[col] = df[col].fillna(median_val)
                    cleaning_log['nulls_filled'][col] = {
                        'method': 'median',
                        'value': float(median_val),
                        'count': int(null_count)
                    }
                    logger.info(f"Filled {null_count} nulls in {col} with median: {median_val}")
                else:
                    # String → isi dengan 'Tidak diketahui'
                    df[col] = df[col].fillna('Tidak diketahui')
                    cleaning_log['nulls_filled'][col] = {
                        'method': 'constant',
                        'value': 'Tidak diketahui',
                        'count': int(null_count)
                    }
                    logger.info(f"Filled {null_count} nulls in {col} with 'Tidak diketahui'")

            cleaning_log['final_rows'] = len(df)
            logger.info(f"✅ Data cleansing complete! Final rows: {len(df)}")

            # Save clean file (opsional)
            clean_path = file_path.replace('.', '_clean.')

            # Determine file extension
            if file_ext in ['.xlsx', '.xls']:
                df.to_excel(clean_path, index=False)
            else:
                df.to_csv(clean_path, index=False, encoding='utf-8')

            logger.info(f"Clean file saved to: {clean_path}")

            return {
                'df': df,
                'cleaning_log': cleaning_log,
                'clean_path': clean_path
            }

        except Exception as e:
            logger.error(f"Error in data cleansing: {str(e)}")
            raise


if __name__ == "__main__":
    # Test
    cleaner = DataCleaner()
    result = cleaner.run("test_data.csv")
    print(result['cleaning_log'])
