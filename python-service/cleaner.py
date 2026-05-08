"""
Data Cleaner Module
Membersihkan data CSV/Excel dengan Pandas pipeline
Output cleaning_log berisi detail lengkap setiap langkah untuk UI report
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
    Setiap langkah dicatat detail ke cleaning_steps untuk transparansi di UI
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
            'empty_rows_removed': 0,
            'nulls_filled': {},
            'type_conversions': [],
            'anomalies_flagged': [],
            'whitespace_columns': [],
            'total_issues_found': 0,
            'cleaning_steps': []
        }

        try:
            # ═══════════════════════════════════════════
            # Step 1: Deteksi encoding
            # ═══════════════════════════════════════════
            logger.info("Step 1: Detecting encoding...")
            with open(file_path, 'rb') as f:
                result = chardet.detect(f.read())
                encoding = result['encoding']
                confidence = result['confidence']

            logger.info(f"Detected encoding: {encoding} (confidence: {confidence:.2f})")

            cleaning_log['cleaning_steps'].append({
                'step': 1,
                'action': 'Deteksi Encoding',
                'description': f'Encoding terdeteksi: {encoding} (confidence {confidence:.0%})',
                'impact': 'none' if encoding and encoding.lower() in ['utf-8', 'ascii'] else 'low',
                'affected_rows': 0,
                'affected_columns': [],
                'detail': f'File menggunakan encoding {encoding}. ' + (
                    'Encoding sudah standar UTF-8/ASCII, tidak perlu konversi.' if encoding and encoding.lower() in ['utf-8', 'ascii']
                    else f'Encoding non-standar ({encoding}), dikonversi saat pembacaan.'
                )
            })

            # ═══════════════════════════════════════════
            # Step 2: Baca file berdasarkan ekstensi
            # ═══════════════════════════════════════════
            logger.info("Step 2: Reading file...")
            file_ext = Path(file_path).suffix.lower()

            if file_ext in ['.xlsx', '.xls']:
                df = pd.read_excel(file_path)
            else:
                df = pd.read_csv(file_path, encoding=encoding)

            cleaning_log['original_rows'] = len(df)
            original_cols = list(df.columns)
            logger.info(f"Original rows: {len(df)}")

            cleaning_log['cleaning_steps'].append({
                'step': 2,
                'action': 'Baca File',
                'description': f'Berhasil membaca {len(df):,} baris × {len(df.columns)} kolom dari file {file_ext}',
                'impact': 'none',
                'affected_rows': len(df),
                'affected_columns': original_cols,
                'detail': f'Format: {file_ext.upper().replace(".", "")}, Kolom: {", ".join(original_cols[:10])}{"..." if len(original_cols) > 10 else ""}'
            })

            # ═══════════════════════════════════════════
            # Step 3: Strip whitespace semua string columns
            # ═══════════════════════════════════════════
            logger.info("Step 3: Stripping whitespace...")
            string_cols = df.select_dtypes(include=['object']).columns
            trimmed_cols = list(string_cols)
            for col in string_cols:
                df[col] = df[col].astype(str).str.strip()

            cleaning_log['whitespace_columns'] = trimmed_cols

            cleaning_log['cleaning_steps'].append({
                'step': 3,
                'action': 'Trim Whitespace',
                'description': f'Whitespace (spasi di awal/akhir) dihapus dari {len(trimmed_cols)} kolom teks',
                'impact': 'low' if len(trimmed_cols) > 0 else 'none',
                'affected_rows': len(df),
                'affected_columns': trimmed_cols,
                'detail': f'Kolom yang di-trim: {", ".join(trimmed_cols[:8])}{"..." if len(trimmed_cols) > 8 else ""}' if trimmed_cols else 'Tidak ada kolom teks untuk di-trim'
            })

            # ═══════════════════════════════════════════
            # Step 4: Hapus baris yang SEMUA kolomnya kosong
            # ═══════════════════════════════════════════
            logger.info("Step 4: Removing empty rows...")
            before_count = len(df)
            df = df.dropna(how='all')
            empty_removed = before_count - len(df)
            cleaning_log['empty_rows_removed'] = empty_removed
            logger.info(f"Removed {empty_removed} completely empty rows")

            cleaning_log['cleaning_steps'].append({
                'step': 4,
                'action': 'Hapus Baris Kosong',
                'description': f'{empty_removed} baris yang seluruh kolomnya kosong (NaN) telah dihapus' if empty_removed > 0 else 'Tidak ditemukan baris kosong',
                'impact': 'medium' if empty_removed > 0 else 'none',
                'affected_rows': empty_removed,
                'affected_columns': original_cols if empty_removed > 0 else [],
                'detail': f'Baris dengan semua {len(original_cols)} kolom bernilai NaN/kosong dianggap data sampah dan dihapus.' if empty_removed > 0 else 'Semua baris memiliki minimal 1 kolom berisi data.'
            })

            # ═══════════════════════════════════════════
            # Step 5: Hapus duplikat exact
            # ═══════════════════════════════════════════
            logger.info("Step 5: Removing exact duplicates...")
            before_count = len(df)
            df = df.drop_duplicates()
            duplicates_removed = before_count - len(df)
            cleaning_log['duplicates_removed'] = duplicates_removed
            logger.info(f"Removed {duplicates_removed} duplicate rows")

            cleaning_log['cleaning_steps'].append({
                'step': 5,
                'action': 'Hapus Duplikat',
                'description': f'{duplicates_removed} baris duplikat identik dihapus' if duplicates_removed > 0 else 'Tidak ditemukan baris duplikat',
                'impact': 'high' if duplicates_removed > len(df) * 0.05 else ('medium' if duplicates_removed > 0 else 'none'),
                'affected_rows': duplicates_removed,
                'affected_columns': original_cols if duplicates_removed > 0 else [],
                'detail': f'Baris yang semua kolomnya sama persis (exact match) dianggap duplikat. {duplicates_removed} baris dihapus ({duplicates_removed/before_count*100:.1f}% dari data).' if duplicates_removed > 0 else 'Setiap baris unik, tidak ada duplikasi data.'
            })

            # ═══════════════════════════════════════════
            # Step 6: Deteksi dan konversi tipe data otomatis
            # ═══════════════════════════════════════════
            logger.info("Step 6: Converting data types...")
            conversion_details = []
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
                        failed_count = len(df) - non_null_count
                        df[col] = converted
                        conv_info = {
                            'column': col,
                            'to_type': 'numeric',
                            'success_rate': f"{(non_null_count/len(df)*100):.1f}%",
                            'failed_values': int(failed_count)
                        }
                        cleaning_log['type_conversions'].append(conv_info)
                        conversion_details.append(f'{col} → numeric ({conv_info["success_rate"]} berhasil, {failed_count} gagal → NaN)')
                        logger.info(f"Converted {col} to numeric ({non_null_count}/{len(df)} values)")

                except Exception as e:
                    logger.warning(f"Could not convert {col} to numeric: {e}")

            cleaning_log['cleaning_steps'].append({
                'step': 6,
                'action': 'Konversi Tipe Data',
                'description': f'{len(conversion_details)} kolom dikonversi dari teks ke angka' if conversion_details else 'Tidak ada kolom yang perlu dikonversi',
                'impact': 'medium' if conversion_details else 'none',
                'affected_rows': len(df) if conversion_details else 0,
                'affected_columns': [c['column'] for c in cleaning_log['type_conversions']],
                'detail': '; '.join(conversion_details) if conversion_details else 'Semua kolom sudah memiliki tipe data yang sesuai (numerik/teks).'
            })

            # ═══════════════════════════════════════════
            # Step 7: Handle nilai negatif di kolom numeric
            # ═══════════════════════════════════════════
            logger.info("Step 7: Flagging negative values...")
            numeric_cols = df.select_dtypes(include=[np.number]).columns
            anomaly_details = []
            for col in numeric_cols:
                negative_count = (df[col] < 0).sum()
                if negative_count > 0:
                    anomalies = {
                        'column': col,
                        'issue': 'negative_values',
                        'count': int(negative_count),
                        'percentage': f"{(negative_count/len(df)*100):.2f}%",
                        'sample_values': df[df[col] < 0][col].head(3).tolist()
                    }
                    cleaning_log['anomalies_flagged'].append(anomalies)
                    anomaly_details.append(f'{col}: {negative_count} nilai negatif ({anomalies["percentage"]})')
                    logger.info(f"Flagged {negative_count} negative values in {col}")

            cleaning_log['cleaning_steps'].append({
                'step': 7,
                'action': 'Deteksi Anomali',
                'description': f'{len(anomaly_details)} kolom memiliki nilai negatif yang mencurigakan' if anomaly_details else 'Tidak ditemukan anomali nilai negatif',
                'impact': 'high' if any(a['count'] > len(df) * 0.1 for a in cleaning_log['anomalies_flagged']) else ('medium' if anomaly_details else 'none'),
                'affected_rows': sum(a['count'] for a in cleaning_log['anomalies_flagged']),
                'affected_columns': [a['column'] for a in cleaning_log['anomalies_flagged']],
                'detail': '; '.join(anomaly_details) if anomaly_details else 'Semua nilai numerik berada dalam rentang wajar (≥ 0). Tidak ada anomali terdeteksi.'
            })

            # ═══════════════════════════════════════════
            # Step 8: Isi null values
            # ═══════════════════════════════════════════
            logger.info("Step 8: Filling null values...")
            null_details = []
            total_nulls_filled = 0
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
                        'count': int(null_count),
                        'percentage': f"{(null_count/len(df)*100):.1f}%",
                        'reason': f'Kolom numerik dengan {null_count} nilai kosong diisi median ({median_val:,.2f}) agar tidak bias oleh outlier'
                    }
                    null_details.append(f'{col}: {null_count} null → median ({median_val:,.2f})')
                    logger.info(f"Filled {null_count} nulls in {col} with median: {median_val}")
                else:
                    # String → isi dengan 'Tidak diketahui'
                    df[col] = df[col].fillna('Tidak diketahui')
                    cleaning_log['nulls_filled'][col] = {
                        'method': 'constant',
                        'value': 'Tidak diketahui',
                        'count': int(null_count),
                        'percentage': f"{(null_count/len(df)*100):.1f}%",
                        'reason': f'Kolom kategorikal dengan {null_count} nilai kosong diisi "Tidak diketahui" untuk menjaga integritas data'
                    }
                    null_details.append(f'{col}: {null_count} null → "Tidak diketahui"')
                    logger.info(f"Filled {null_count} nulls in {col} with 'Tidak diketahui'")

                total_nulls_filled += null_count

            cleaning_log['cleaning_steps'].append({
                'step': 8,
                'action': 'Isi Nilai Kosong (Null)',
                'description': f'{total_nulls_filled} nilai kosong di {len(null_details)} kolom telah diisi' if null_details else 'Tidak ditemukan nilai kosong (null)',
                'impact': 'high' if total_nulls_filled > len(df) * 0.1 else ('medium' if null_details else 'none'),
                'affected_rows': total_nulls_filled,
                'affected_columns': list(cleaning_log['nulls_filled'].keys()),
                'detail': '; '.join(null_details) if null_details else 'Data sudah lengkap, semua sel terisi.'
            })

            # ═══════════════════════════════════════════
            # Finalize
            # ═══════════════════════════════════════════
            cleaning_log['final_rows'] = len(df)
            cleaning_log['total_issues_found'] = (
                cleaning_log['duplicates_removed'] +
                cleaning_log['empty_rows_removed'] +
                total_nulls_filled +
                sum(a['count'] for a in cleaning_log['anomalies_flagged']) +
                sum(c.get('failed_values', 0) for c in cleaning_log['type_conversions'])
            )

            logger.info(f"✅ Data cleansing complete! Final rows: {len(df)}, Total issues: {cleaning_log['total_issues_found']}")

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
