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
            # Step 3.5: Standardisasi nama kolom
            # ═══════════════════════════════════════════
            logger.info("Step 3.5: Standardizing column names...")
            original_col_names = list(df.columns)
            new_col_names = []
            renamed_cols = []
            for col in df.columns:
                import re as _re
                new_name = col.strip()
                new_name = new_name.replace(' ', '_').replace('-', '_')
                new_name = _re.sub(r'[^a-zA-Z0-9_]', '', new_name)
                new_name = _re.sub(r'_+', '_', new_name).strip('_')
                if not new_name:
                    new_name = f'col_{len(new_col_names)}'
                new_col_names.append(new_name)
                if new_name != col:
                    renamed_cols.append(f'{col} → {new_name}')
            df.columns = new_col_names

            cleaning_log['cleaning_steps'].append({
                'step': 3.5,
                'action': 'Standardisasi Nama Kolom',
                'description': f'{len(renamed_cols)} kolom di-rename ke format standar' if renamed_cols else 'Nama kolom sudah standar',
                'impact': 'low' if renamed_cols else 'none',
                'affected_rows': 0,
                'affected_columns': renamed_cols[:10],
                'detail': '; '.join(renamed_cols[:8]) if renamed_cols else 'Semua nama kolom sudah bersih (tidak ada spasi atau karakter khusus).'
            })
            original_cols = list(df.columns)  # update reference

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
            # Step 5.5: Deteksi kolom zero-variance
            # ═══════════════════════════════════════════
            logger.info("Step 5.5: Detecting zero-variance columns...")
            zero_var_cols = []
            for col in df.columns:
                if df[col].nunique() <= 1:
                    zero_var_cols.append(col)
            # Don't drop — just flag for awareness
            cleaning_log['zero_variance_columns'] = zero_var_cols

            cleaning_log['cleaning_steps'].append({
                'step': 5.5,
                'action': 'Deteksi Kolom Konstan',
                'description': f'{len(zero_var_cols)} kolom hanya memiliki 1 nilai unik (tidak informatif)' if zero_var_cols else 'Semua kolom memiliki variasi data',
                'impact': 'medium' if zero_var_cols else 'none',
                'affected_rows': len(df) if zero_var_cols else 0,
                'affected_columns': zero_var_cols,
                'detail': f'Kolom dengan 1 nilai: {", ".join(zero_var_cols[:5])}. Kolom ini tidak memberikan informasi analitis karena isinya identik di semua baris.' if zero_var_cols else 'Setiap kolom memiliki variasi nilai yang cukup untuk analisis.'
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
            # Step 6.5: Clean invalid characters
            # ═══════════════════════════════════════════
            logger.info("Step 6.5: Cleaning invalid characters...")
            import re as _re2
            invalid_char_cols = []
            string_cols_v2 = df.select_dtypes(include=['object']).columns
            for col in string_cols_v2:
                # Fix encoding artifacts: â€™ → ', â€œ → ", etc
                original_vals = df[col].copy()
                df[col] = df[col].astype(str).str.replace(r'â€™', "'", regex=False)
                df[col] = df[col].str.replace(r'â€œ', '"', regex=False)
                df[col] = df[col].str.replace(r'â€\x9d', '"', regex=False)
                # Remove non-printable chars (except newline/tab)
                df[col] = df[col].str.replace(r'[^\x20-\x7E\xA0-\xFF\n\t]', '', regex=True)
                if not df[col].equals(original_vals):
                    invalid_char_cols.append(col)

            cleaning_log['cleaning_steps'].append({
                'step': 6.5,
                'action': 'Bersihkan Karakter Invalid',
                'description': f'Karakter non-printable dan encoding artifacts dibersihkan dari {len(invalid_char_cols)} kolom' if invalid_char_cols else 'Tidak ditemukan karakter invalid',
                'impact': 'low' if invalid_char_cols else 'none',
                'affected_rows': len(df) if invalid_char_cols else 0,
                'affected_columns': invalid_char_cols,
                'detail': f'Kolom yang dibersihkan: {", ".join(invalid_char_cols[:5])}. Encoding artifacts (â€™, â€œ, dll) dikonversi ke karakter standar.' if invalid_char_cols else 'Semua data teks sudah bersih dari karakter invalid.'
            })

            # ═══════════════════════════════════════════
            # Step 7: Deteksi Anomali & Outlier (IQR method)
            # ═══════════════════════════════════════════
            logger.info("Step 7: Detecting anomalies and outliers (IQR)...")
            numeric_cols = df.select_dtypes(include=[np.number]).columns
            anomaly_details = []
            for col in numeric_cols:
                s = df[col].dropna()
                if len(s) < 10:
                    continue
                # Negative value check
                negative_count = (s < 0).sum()
                if negative_count > 0:
                    cleaning_log['anomalies_flagged'].append({
                        'column': col, 'issue': 'negative_values',
                        'count': int(negative_count),
                        'percentage': f"{(negative_count/len(df)*100):.2f}%",
                        'sample_values': df[df[col] < 0][col].head(3).tolist()
                    })
                    anomaly_details.append(f'{col}: {negative_count} nilai negatif')
                # IQR outlier detection
                q1, q3 = s.quantile(0.25), s.quantile(0.75)
                iqr = q3 - q1
                if iqr > 0:
                    lower = q1 - 1.5 * iqr
                    upper = q3 + 1.5 * iqr
                    outlier_count = int(((s < lower) | (s > upper)).sum())
                    if outlier_count > 0:
                        cleaning_log['anomalies_flagged'].append({
                            'column': col, 'issue': 'iqr_outliers',
                            'count': outlier_count,
                            'percentage': f"{(outlier_count/len(s)*100):.2f}%",
                            'bounds': f'[{lower:,.2f}, {upper:,.2f}]',
                            'sample_values': s[(s < lower) | (s > upper)].head(3).tolist()
                        })
                        anomaly_details.append(f'{col}: {outlier_count} outlier (IQR bounds: {lower:,.1f} – {upper:,.1f})')

            cleaning_log['cleaning_steps'].append({
                'step': 7,
                'action': 'Deteksi Anomali & Outlier',
                'description': f'{len(anomaly_details)} masalah terdeteksi (nilai negatif + IQR outlier)' if anomaly_details else 'Tidak ditemukan anomali',
                'impact': 'high' if any(a['count'] > len(df) * 0.1 for a in cleaning_log['anomalies_flagged']) else ('medium' if anomaly_details else 'none'),
                'affected_rows': sum(a['count'] for a in cleaning_log['anomalies_flagged']),
                'affected_columns': list(set(a['column'] for a in cleaning_log['anomalies_flagged'])),
                'detail': '; '.join(anomaly_details) if anomaly_details else 'Semua nilai numerik berada dalam rentang wajar. Tidak ada outlier terdeteksi berdasarkan metode IQR (Q1-1.5×IQR, Q3+1.5×IQR).'
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
