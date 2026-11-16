<?php

namespace App\Services\Analysis;

/**
 * DataCleaner — port PHP dari python-service/cleaner.py
 *
 * 9 tahap cleansing dengan log detail per langkah. Struktur cleaning_log
 * sengaja dipertahankan identik agar DataQualityCard.vue tidak perlu diubah.
 */
class DataCleaner
{
    /**
     * Jalankan pipeline cleansing.
     *
     * @return array{table: DataTable, cleaning_log: array<string, mixed>, clean_path: ?string}
     */
    public function run(string $filePath, ?string $cleanPath = null): array
    {
        $log = [
            'original_rows' => 0,
            'final_rows' => 0,
            'duplicates_removed' => 0,
            'empty_rows_removed' => 0,
            'nulls_filled' => [],
            'type_conversions' => [],
            'anomalies_flagged' => [],
            'whitespace_columns' => [],
            'zero_variance_columns' => [],
            'total_issues_found' => 0,
            'cleaning_steps' => [],
        ];

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // ── Step 1: Deteksi format & encoding ──
        $encoding = in_array($ext, ['csv', 'txt'], true)
            ? $this->detectEncoding($filePath)
            : 'native (spreadsheet)';
        $isStandard = in_array(strtolower((string) $encoding), ['utf-8', 'ascii'], true);

        $log['cleaning_steps'][] = [
            'step' => 1,
            'action' => 'Deteksi Encoding',
            'description' => "Encoding terdeteksi: {$encoding}",
            'impact' => $isStandard ? 'none' : 'low',
            'affected_rows' => 0,
            'affected_columns' => [],
            'detail' => $isStandard
                ? 'Encoding sudah standar UTF-8/ASCII, tidak perlu konversi.'
                : "Encoding non-standar ({$encoding}), dikonversi saat pembacaan.",
        ];

        // ── Step 2: Baca file ──
        $table = DataTable::fromFile($filePath);
        $log['original_rows'] = $table->count();
        $originalCols = $table->columns();

        $log['cleaning_steps'][] = [
            'step' => 2,
            'action' => 'Baca File',
            'description' => sprintf(
                'Berhasil membaca %s baris × %d kolom dari file .%s',
                number_format($table->count()), count($table->columns()), $ext
            ),
            'impact' => 'none',
            'affected_rows' => $table->count(),
            'affected_columns' => $originalCols,
            'detail' => sprintf(
                'Format: %s, Kolom: %s%s',
                strtoupper($ext),
                implode(', ', array_slice($originalCols, 0, 10)),
                count($originalCols) > 10 ? '...' : ''
            ),
        ];

        // ── Step 3: Trim whitespace ──
        $trimmedCols = [];
        foreach ($table->stringColumns() as $col) {
            $trimmedCols[] = $col;
            $table->mapColumn($col, fn ($v) => $v === null ? null : trim((string) $v));
        }
        $log['whitespace_columns'] = $trimmedCols;

        $log['cleaning_steps'][] = [
            'step' => 3,
            'action' => 'Trim Whitespace',
            'description' => sprintf('Whitespace (spasi di awal/akhir) dihapus dari %d kolom teks', count($trimmedCols)),
            'impact' => $trimmedCols ? 'low' : 'none',
            'affected_rows' => $table->count(),
            'affected_columns' => $trimmedCols,
            'detail' => $trimmedCols
                ? 'Kolom yang di-trim: ' . implode(', ', array_slice($trimmedCols, 0, 8)) . (count($trimmedCols) > 8 ? '...' : '')
                : 'Tidak ada kolom teks untuk di-trim',
        ];

        // ── Step 3.5: Standardisasi nama kolom ──
        $mapping = [];
        $renamed = [];
        foreach ($table->columns() as $i => $col) {
            $new = trim($col);
            $new = str_replace([' ', '-'], '_', $new);
            $new = preg_replace('/[^A-Za-z0-9_]/', '', $new) ?? '';
            $new = trim(preg_replace('/_+/', '_', $new) ?? '', '_');
            if ($new === '') {
                $new = 'kolom_' . $i;
            }
            if ($new !== $col) {
                $mapping[$col] = $new;
                $renamed[] = "{$col} → {$new}";
            }
        }
        $table->renameColumns($mapping);
        $originalCols = $table->columns();

        $log['cleaning_steps'][] = [
            'step' => 3.5,
            'action' => 'Standardisasi Nama Kolom',
            'description' => $renamed
                ? sprintf('%d kolom di-rename ke format standar', count($renamed))
                : 'Nama kolom sudah standar',
            'impact' => $renamed ? 'low' : 'none',
            'affected_rows' => 0,
            'affected_columns' => array_slice($renamed, 0, 10),
            'detail' => $renamed
                ? implode('; ', array_slice($renamed, 0, 8))
                : 'Semua nama kolom sudah bersih (tidak ada spasi atau karakter khusus).',
        ];

        // ── Step 4: Hapus baris kosong ──
        $emptyRemoved = $table->dropEmptyRows();
        $log['empty_rows_removed'] = $emptyRemoved;

        $log['cleaning_steps'][] = [
            'step' => 4,
            'action' => 'Hapus Baris Kosong',
            'description' => $emptyRemoved > 0
                ? "{$emptyRemoved} baris yang seluruh kolomnya kosong telah dihapus"
                : 'Tidak ditemukan baris kosong',
            'impact' => $emptyRemoved > 0 ? 'medium' : 'none',
            'affected_rows' => $emptyRemoved,
            'affected_columns' => $emptyRemoved > 0 ? $originalCols : [],
            'detail' => $emptyRemoved > 0
                ? sprintf('Baris dengan semua %d kolom bernilai kosong dianggap data sampah dan dihapus.', count($originalCols))
                : 'Semua baris memiliki minimal 1 kolom berisi data.',
        ];

        // ── Step 5: Hapus duplikat ──
        $before = $table->count();
        $duplicatesRemoved = $table->dropDuplicates();
        $log['duplicates_removed'] = $duplicatesRemoved;

        $pctOfData = $before > 0 ? $duplicatesRemoved / $before * 100 : 0;
        $log['cleaning_steps'][] = [
            'step' => 5,
            'action' => 'Hapus Duplikat',
            'description' => $duplicatesRemoved > 0
                ? "{$duplicatesRemoved} baris duplikat identik dihapus"
                : 'Tidak ditemukan baris duplikat',
            'impact' => $duplicatesRemoved > $before * 0.05 ? 'high' : ($duplicatesRemoved > 0 ? 'medium' : 'none'),
            'affected_rows' => $duplicatesRemoved,
            'affected_columns' => $duplicatesRemoved > 0 ? $originalCols : [],
            'detail' => $duplicatesRemoved > 0
                ? sprintf(
                    'Baris yang semua kolomnya sama persis dianggap duplikat. %d baris dihapus (%.1f%% dari data).',
                    $duplicatesRemoved, $pctOfData
                )
                : 'Setiap baris unik, tidak ada duplikasi data.',
        ];

        // ── Step 5.5: Deteksi kolom konstan ──
        $zeroVarCols = [];
        foreach ($table->columns() as $col) {
            $summary = $table->columnSummary($col);
            if ($summary['unique'] <= 1) {
                $zeroVarCols[] = $col;
            }
        }
        $log['zero_variance_columns'] = $zeroVarCols;

        $log['cleaning_steps'][] = [
            'step' => 5.5,
            'action' => 'Deteksi Kolom Konstan',
            'description' => $zeroVarCols
                ? count($zeroVarCols) . ' kolom hanya memiliki 1 nilai unik (tidak informatif)'
                : 'Semua kolom memiliki variasi data',
            'impact' => $zeroVarCols ? 'medium' : 'none',
            'affected_rows' => $zeroVarCols ? $table->count() : 0,
            'affected_columns' => $zeroVarCols,
            'detail' => $zeroVarCols
                ? 'Kolom dengan 1 nilai: ' . implode(', ', array_slice($zeroVarCols, 0, 5))
                    . '. Kolom ini tidak memberikan informasi analitis karena isinya identik di semua baris.'
                : 'Setiap kolom memiliki variasi nilai yang cukup untuk analisis.',
        ];

        // ── Step 6: Konversi tipe data ──
        $conversionDetails = [];
        $rowCount = $table->count();

        foreach ($table->columns() as $col) {
            if ($table->type($col) === 'numeric') {
                continue;
            }

            // Hitung berapa nilai yang bisa jadi angka
            $converted = 0;
            $failed = 0;
            foreach ($table->column($col) as $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                if (DataTable::tryNumeric($v) !== null) {
                    $converted++;
                } else {
                    $failed++;
                }
            }

            // Konversi hanya jika >50% nilai berhasil, sama seperti versi Python
            if ($converted > $rowCount * 0.5) {
                $table->mapColumn($col, function ($v) {
                    if ($v === null || $v === '') {
                        return null;
                    }

                    return DataTable::tryNumeric($v);
                });

                $successRate = $rowCount > 0 ? $converted / $rowCount * 100 : 0;
                $log['type_conversions'][] = [
                    'column' => $col,
                    'to_type' => 'numeric',
                    'success_rate' => sprintf('%.1f%%', $successRate),
                    'failed_values' => $failed,
                ];
                $conversionDetails[] = sprintf(
                    '%s → numeric (%.1f%% berhasil, %d gagal → kosong)',
                    $col, $successRate, $failed
                );
            }
        }

        $log['cleaning_steps'][] = [
            'step' => 6,
            'action' => 'Konversi Tipe Data',
            'description' => $conversionDetails
                ? count($conversionDetails) . ' kolom dikonversi dari teks ke angka'
                : 'Tidak ada kolom yang perlu dikonversi',
            'impact' => $conversionDetails ? 'medium' : 'none',
            'affected_rows' => $conversionDetails ? $rowCount : 0,
            'affected_columns' => array_column($log['type_conversions'], 'column'),
            'detail' => $conversionDetails
                ? implode('; ', $conversionDetails)
                : 'Semua kolom sudah memiliki tipe data yang sesuai (numerik/teks).',
        ];

        // ── Step 6.5: Bersihkan karakter invalid ──
        $invalidCharCols = [];
        foreach ($table->stringColumns() as $col) {
            $changed = false;
            $table->mapColumn($col, function ($v) use (&$changed) {
                if ($v === null) {
                    return null;
                }
                $original = (string) $v;
                // Perbaiki artefak encoding umum
                $clean = str_replace(
                    ['â€™', 'â€œ', 'â€\x9d', 'â€"', 'â€"'],
                    ["'", '"', '"', '—', '–'],
                    $original
                );
                // Buang karakter non-printable kecuali newline/tab
                $clean = preg_replace('/[^\x20-\x7E\xA0-\xFF\n\t]/u', '', $clean) ?? $clean;
                if ($clean !== $original) {
                    $changed = true;
                }

                return $clean;
            });
            if ($changed) {
                $invalidCharCols[] = $col;
            }
        }

        $log['cleaning_steps'][] = [
            'step' => 6.5,
            'action' => 'Bersihkan Karakter Invalid',
            'description' => $invalidCharCols
                ? sprintf('Karakter non-printable dan encoding artifacts dibersihkan dari %d kolom', count($invalidCharCols))
                : 'Tidak ditemukan karakter invalid',
            'impact' => $invalidCharCols ? 'low' : 'none',
            'affected_rows' => $invalidCharCols ? $rowCount : 0,
            'affected_columns' => $invalidCharCols,
            'detail' => $invalidCharCols
                ? 'Kolom yang dibersihkan: ' . implode(', ', array_slice($invalidCharCols, 0, 5))
                    . '. Encoding artifacts (â€™, â€œ, dll) dikonversi ke karakter standar.'
                : 'Semua data teks sudah bersih dari karakter invalid.',
        ];

        // ── Step 7: Deteksi anomali & outlier (IQR) ──
        $anomalyDetails = [];
        foreach ($table->numericColumns() as $col) {
            $vals = $table->numericColumn($col);
            if (count($vals) < 10) {
                continue;
            }

            // Nilai negatif
            $negatives = array_filter($vals, fn ($v) => $v < 0);
            if ($negatives !== []) {
                $log['anomalies_flagged'][] = [
                    'column' => $col,
                    'issue' => 'negative_values',
                    'count' => count($negatives),
                    'percentage' => sprintf('%.2f%%', count($negatives) / $rowCount * 100),
                    'sample_values' => array_slice(array_values($negatives), 0, 3),
                ];
                $anomalyDetails[] = "{$col}: " . count($negatives) . ' nilai negatif';
            }

            // Outlier IQR
            sort($vals);
            $q1 = DataTable::quantile($vals, 0.25);
            $q3 = DataTable::quantile($vals, 0.75);
            $iqr = $q3 - $q1;
            if ($iqr > 0) {
                $lower = $q1 - 1.5 * $iqr;
                $upper = $q3 + 1.5 * $iqr;
                $outliers = array_values(array_filter($vals, fn ($v) => $v < $lower || $v > $upper));
                if ($outliers !== []) {
                    $log['anomalies_flagged'][] = [
                        'column' => $col,
                        'issue' => 'iqr_outliers',
                        'count' => count($outliers),
                        'percentage' => sprintf('%.2f%%', count($outliers) / count($vals) * 100),
                        'bounds' => sprintf('[%s, %s]', number_format($lower, 2), number_format($upper, 2)),
                        'sample_values' => array_slice($outliers, 0, 3),
                    ];
                    $anomalyDetails[] = sprintf(
                        '%s: %d outlier (IQR bounds: %s – %s)',
                        $col, count($outliers), number_format($lower, 1), number_format($upper, 1)
                    );
                }
            }
        }

        $highImpact = false;
        foreach ($log['anomalies_flagged'] as $a) {
            if ($a['count'] > $rowCount * 0.1) {
                $highImpact = true;
                break;
            }
        }

        $log['cleaning_steps'][] = [
            'step' => 7,
            'action' => 'Deteksi Anomali & Outlier',
            'description' => $anomalyDetails
                ? count($anomalyDetails) . ' masalah terdeteksi (nilai negatif + IQR outlier)'
                : 'Tidak ditemukan anomali',
            'impact' => $highImpact ? 'high' : ($anomalyDetails ? 'medium' : 'none'),
            'affected_rows' => array_sum(array_column($log['anomalies_flagged'], 'count')),
            'affected_columns' => array_values(array_unique(array_column($log['anomalies_flagged'], 'column'))),
            'detail' => $anomalyDetails
                ? implode('; ', $anomalyDetails)
                : 'Semua nilai numerik berada dalam rentang wajar. Tidak ada outlier terdeteksi berdasarkan metode IQR (Q1-1.5×IQR, Q3+1.5×IQR).',
        ];

        // ── Step 8: Isi nilai kosong ──
        $filled = $table->fillNulls('Tidak diketahui');
        $nullDetails = [];
        $totalNullsFilled = 0;

        foreach ($filled as $col => $info) {
            $pct = $rowCount > 0 ? $info['count'] / $rowCount * 100 : 0;
            if ($info['method'] === 'median') {
                $log['nulls_filled'][$col] = [
                    'method' => 'median',
                    'value' => (float) $info['value'],
                    'count' => (int) $info['count'],
                    'percentage' => sprintf('%.1f%%', $pct),
                    'reason' => sprintf(
                        'Kolom numerik dengan %d nilai kosong diisi median (%s) agar tidak bias oleh outlier',
                        $info['count'], number_format((float) $info['value'], 2)
                    ),
                ];
                $nullDetails[] = sprintf('%s: %d null → median (%s)', $col, $info['count'], number_format((float) $info['value'], 2));
            } else {
                $log['nulls_filled'][$col] = [
                    'method' => 'constant',
                    'value' => 'Tidak diketahui',
                    'count' => (int) $info['count'],
                    'percentage' => sprintf('%.1f%%', $pct),
                    'reason' => sprintf(
                        'Kolom kategorikal dengan %d nilai kosong diisi "Tidak diketahui" untuk menjaga integritas data',
                        $info['count']
                    ),
                ];
                $nullDetails[] = "{$col}: {$info['count']} null → \"Tidak diketahui\"";
            }
            $totalNullsFilled += (int) $info['count'];
        }

        $log['cleaning_steps'][] = [
            'step' => 8,
            'action' => 'Isi Nilai Kosong (Null)',
            'description' => $nullDetails
                ? sprintf('%d nilai kosong di %d kolom telah diisi', $totalNullsFilled, count($nullDetails))
                : 'Tidak ditemukan nilai kosong (null)',
            'impact' => $totalNullsFilled > $rowCount * 0.1 ? 'high' : ($nullDetails ? 'medium' : 'none'),
            'affected_rows' => $totalNullsFilled,
            'affected_columns' => array_keys($log['nulls_filled']),
            'detail' => $nullDetails
                ? implode('; ', $nullDetails)
                : 'Data sudah lengkap, semua sel terisi.',
        ];

        // ── Finalisasi ──
        $log['final_rows'] = $table->count();
        $log['total_issues_found'] = $log['duplicates_removed']
            + $log['empty_rows_removed']
            + $totalNullsFilled
            + array_sum(array_column($log['anomalies_flagged'], 'count'))
            + array_sum(array_column($log['type_conversions'], 'failed_values'));

        // Simpan file bersih jika diminta
        if ($cleanPath !== null) {
            $this->writeCleanFile($table, $cleanPath, $ext);
        }

        return [
            'table' => $table,
            'cleaning_log' => $log,
            'clean_path' => $cleanPath,
        ];
    }

    /**
     * Simpan hasil cleaning. CSV tetap CSV; Excel tetap Excel.
     */
    private function writeCleanFile(DataTable $table, string $path, string $ext): void
    {
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($table->columns(), null, 'A1', true);

            $rowNum = 2;
            foreach ($table->rows() as $row) {
                $values = [];
                foreach ($table->columns() as $col) {
                    $values[] = $row[$col] ?? null;
                }
                $sheet->fromArray($values, null, 'A' . $rowNum, true);
                $rowNum++;
            }

            \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
            $spreadsheet->disconnectWorksheets();

            return;
        }

        $table->toCsv($path);
    }

    /**
     * Deteksi encoding file teks. chardet versi ringkas: cek BOM dan utf8 validity.
     */
    private function detectEncoding(string $path): string
    {
        $head = (string) file_get_contents($path, false, null, 0, 4096);

        if (str_starts_with($head, "\xEF\xBB\xBF")) {
            return 'UTF-8 (BOM)';
        }
        if (str_starts_with($head, "\xFF\xFE") || str_starts_with($head, "\xFE\xFF")) {
            return 'UTF-16';
        }

        return mb_check_encoding($head, 'UTF-8') ? 'UTF-8' : 'ISO-8859-1';
    }
}
