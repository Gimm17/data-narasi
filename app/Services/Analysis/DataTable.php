<?php

namespace App\Services\Analysis;

/**
 * DataTable — struktur data kolumnar pengganti pandas.DataFrame.
 *
 * Dipakai oleh DataCleaner, DataAnalyzer, dan ChartBuilder.
 * Alasan dibuat sendiri: shared hosting tidak bisa menjalankan pandas,
 * sehingga seluruh operasi tabel harus tersedia dalam PHP murni.
 *
 * Representasi internal: array baris, setiap baris array asosiatif
 * [nama_kolom => nilai]. Nilai numerik disimpan sebagai int|float,
 * nilai kosong sebagai null, sisanya string.
 */
class DataTable
{
    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    /** @var array<int, string> */
    private array $columns = [];

    /** @var array<string, string> tipe terdeteksi: numeric|string|date|boolean */
    private array $types = [];

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $columns
     */
    public function __construct(array $rows = [], array $columns = [])
    {
        $this->rows = $rows;
        $this->columns = $columns ?: ($rows ? array_keys($rows[0]) : []);
        $this->detectTypes();
    }

    /**
     * Bangun DataTable dari file CSV atau Excel.
     *
     * @throws \RuntimeException
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("File tidak ditemukan: {$path}");
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match (true) {
            in_array($ext, ['xlsx', 'xls'], true) => self::fromSpreadsheet($path),
            default => self::fromCsv($path),
        };
    }

    /**
     * Baca CSV dengan deteksi delimiter sederhana (koma, titik koma, tab).
     */
    private static function fromCsv(string $path): self
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new \RuntimeException("Gagal membuka file: {$path}");
        }

        // Deteksi delimiter dari baris pertama
        $firstLine = (string) fgets($handle);
        rewind($handle);
        $delimiter = self::detectDelimiter($firstLine);

        $header = fgetcsv($handle, 0, $delimiter, '"', '');
        if ($header === false) {
            fclose($handle);
            throw new \RuntimeException('File CSV kosong atau header tidak terbaca.');
        }

        $columns = self::normalizeHeaders($header);
        $rows = [];

        while (($line = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            // Lewati baris kosong total
            if ($line === [null] || count(array_filter($line, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($columns as $i => $name) {
                $row[$name] = self::castValue($line[$i] ?? null);
            }
            $rows[] = $row;
        }

        fclose($handle);

        return new self($rows, $columns);
    }

    /**
     * Baca Excel via PhpSpreadsheet (read-only, tanpa styling).
     */
    private static function fromSpreadsheet(string $path): self
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();

        $columns = [];
        $rows = [];
        $isHeader = true;

        foreach ($sheet->toArray(null, true, false, false) as $line) {
            if ($isHeader) {
                $columns = self::normalizeHeaders($line);
                $isHeader = false;
                continue;
            }

            $hasValue = false;
            foreach ($line as $v) {
                if ($v !== null && trim((string) $v) !== '') {
                    $hasValue = true;
                    break;
                }
            }
            if (!$hasValue) {
                continue;
            }

            $row = [];
            foreach ($columns as $i => $name) {
                $row[$name] = self::castValue($line[$i] ?? null);
            }
            $rows[] = $row;
        }

        return new self($rows, $columns);
    }

    private static function detectDelimiter(string $line): string
    {
        $candidates = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];
        foreach ($candidates as $char => $_) {
            $candidates[$char] = substr_count($line, $char);
        }
        arsort($candidates);
        $best = array_key_first($candidates);

        return ($candidates[$best] ?? 0) > 0 ? $best : ',';
    }

    /**
     * Bersihkan header: buang BOM, trim, dan pastikan unik.
     *
     * @param array<int, mixed> $header
     * @return array<int, string>
     */
    private static function normalizeHeaders(array $header): array
    {
        $columns = [];
        foreach ($header as $i => $name) {
            $name = trim((string) $name);
            $name = preg_replace('/^\xEF\xBB\xBF/', '', $name) ?? $name;
            if ($name === '') {
                $name = 'kolom_' . ($i + 1);
            }

            // Hindari nama duplikat
            $base = $name;
            $n = 2;
            while (in_array($name, $columns, true)) {
                $name = $base . '_' . $n;
                $n++;
            }
            $columns[] = $name;
        }

        return $columns;
    }

    /**
     * Konversi nilai sel: trim, angka → int/float, kosong → null.
     *
     * String yang berbentuk tanggal sengaja TIDAK diubah menjadi angka.
     * Tanpa penjagaan ini "2024-01-15" akan menjadi 20240115 karena
     * pemisah non-digit dibuang.
     */
    private static function castValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        if (self::isDateLike($str)) {
            return $str;
        }

        return self::tryNumeric($str) ?? $str;
    }

    /**
     * Coba parsing string menjadi angka. Mendukung format Indonesia
     * (1.234,56) maupun format internasional (1,234.56).
     */
    public static function tryNumeric(mixed $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return null;
        }

        $str = trim($value);
        if ($str === '') {
            return null;
        }

        // Tanggal/jam bukan angka — jangan biarkan pemisahnya dibuang
        if (self::isDateLike($str) || preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $str)) {
            return null;
        }

        // Buang pemisah ribuan dan simbol mata uang
        $cleaned = preg_replace('/[^\d,.\-]/u', '', $str);
        if ($cleaned === null || $cleaned === '' || $cleaned === '-') {
            return null;
        }

        $hasComma = str_contains($cleaned, ',');
        $hasDot = str_contains($cleaned, '.');

        if ($hasComma && $hasDot) {
            // Format yang dipakai = pemisah desimal adalah yang muncul terakhir
            if (strrpos($cleaned, ',') > strrpos($cleaned, '.')) {
                // 1.234,56 → 1234.56
                $cleaned = str_replace('.', '', $cleaned);
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                // 1,234.56 → 1234.56
                $cleaned = str_replace(',', '', $cleaned);
            }
        } elseif ($hasComma) {
            // Koma diikuti tepat 1-2 digit di ujung → desimal, selain itu ribuan
            if (preg_match('/,\d{1,2}$/', $cleaned)) {
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                $cleaned = str_replace(',', '', $cleaned);
            }
        } elseif ($hasDot) {
            // Beberapa titik → pemisah ribuan; satu titik dengan 3 digit di ujung
            // diperlakukan sebagai ribuan hanya jika ada lebih dari satu titik.
            if (substr_count($cleaned, '.') > 1) {
                $cleaned = str_replace('.', '', $cleaned);
            }
        }

        if (!is_numeric($cleaned)) {
            return null;
        }

        $float = (float) $cleaned;

        return (floor($float) === $float && abs($float) < PHP_INT_MAX) ? (int) $float : $float;
    }

    /**
     * Deteksi tipe tiap kolom berdasarkan mayoritas nilai.
     */
    private function detectTypes(): void
    {
        foreach ($this->columns as $col) {
            $numeric = 0;
            $dateLike = 0;
            $boolean = 0;
            $total = 0;

            foreach ($this->rows as $row) {
                $v = $row[$col] ?? null;
                if ($v === null) {
                    continue;
                }
                $total++;

                if (is_int($v) || is_float($v)) {
                    $numeric++;
                    continue;
                }

                $str = (string) $v;
                if (self::tryNumeric($str) !== null) {
                    $numeric++;
                } elseif (in_array(strtolower($str), ['true', 'false', 'ya', 'tidak', 'yes', 'no'], true)) {
                    $boolean++;
                } elseif (self::isDateLike($str)) {
                    $dateLike++;
                }
            }

            if ($total === 0) {
                $this->types[$col] = 'string';
                continue;
            }

            $this->types[$col] = match (true) {
                $numeric / $total > 0.8 => 'numeric',
                $dateLike / $total > 0.8 => 'date',
                $boolean / $total > 0.8 => 'boolean',
                default => 'string',
            };
        }
    }

    /**
     * Heuristik tanggal: coba beberapa format umum.
     */
    public static function isDateLike(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || strlen($value) < 6) {
            return false;
        }

        // Hanya string yang mengandung pemisah tanggal / nama bulan
        if (!preg_match('/^[\d]{1,4}[-\/\.\s]/', $value) && !preg_match('/[a-zA-Z]{3,}/', $value)) {
            return false;
        }

        $ts = strtotime($value);

        return $ts !== false && $ts !== -1;
    }

    // ───────────────────────── Akses dasar ─────────────────────────

    /** @return array<int, string> */
    public function columns(): array
    {
        return $this->columns;
    }

    /** @return array<int, array<string, mixed>> */
    public function rows(): array
    {
        return $this->rows;
    }

    public function count(): int
    {
        return count($this->rows);
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    public function type(string $column): string
    {
        return $this->types[$column] ?? 'string';
    }

    /** @return array<string, string> */
    public function types(): array
    {
        return $this->types;
    }

    /** @return array<int, string> */
    public function numericColumns(): array
    {
        return array_values(array_filter($this->columns, fn ($c) => $this->type($c) === 'numeric'));
    }

    /** @return array<int, string> */
    public function stringColumns(): array
    {
        return array_values(array_filter($this->columns, fn ($c) => $this->type($c) === 'string'));
    }

    /** @return array<int, string> */
    public function dateColumns(): array
    {
        return array_values(array_filter($this->columns, fn ($c) => $this->type($c) === 'date'));
    }

    /**
     * Ambil satu kolom sebagai array nilai (null dipertahankan).
     *
     * @return array<int, mixed>
     */
    public function column(string $column): array
    {
        $out = [];
        foreach ($this->rows as $row) {
            $out[] = $row[$column] ?? null;
        }

        return $out;
    }

    /**
     * Ambil kolom sebagai float murni, membuang null.
     *
     * @return array<int, float>
     */
    public function numericColumn(string $column): array
    {
        $out = [];
        foreach ($this->column($column) as $v) {
            $n = self::tryNumeric($v);
            if ($n !== null) {
                $out[] = (float) $n;
            }
        }

        return $out;
    }

    // ───────────────────────── Statistik ─────────────────────────

    /** @return array{count:int, nulls:int, unique:int} */
    public function columnSummary(string $column): array
    {
        $nulls = 0;
        $seen = [];

        foreach ($this->column($column) as $v) {
            if ($v === null || $v === '') {
                $nulls++;
                continue;
            }
            $seen[is_scalar($v) ? (string) $v : json_encode($v)] = true;
        }

        return [
            'count' => count($this->rows),
            'nulls' => $nulls,
            'unique' => count($seen),
        ];
    }

    /**
     * Statistik deskriptif untuk satu kolom numerik.
     *
     * @return array<string, float|int>
     */
    public function numericStats(string $column): array
    {
        $vals = $this->numericColumn($column);
        if ($vals === []) {
            return [];
        }

        sort($vals);
        $n = count($vals);
        $sum = array_sum($vals);
        $mean = $sum / $n;
        $median = self::median($vals);
        $std = self::stdDev($vals, $mean);

        return [
            'count' => $n,
            'total' => $sum,
            'mean' => $mean,
            'median' => $median,
            'min' => $vals[0],
            'max' => $vals[$n - 1],
            'std' => $std,
        ];
    }

    public static function median(array $sorted): float
    {
        $n = count($sorted);
        if ($n === 0) {
            return 0.0;
        }
        if ($n % 2 === 1) {
            return (float) $sorted[intdiv($n, 2)];
        }

        return ((float) $sorted[$n / 2 - 1] + (float) $sorted[$n / 2]) / 2;
    }

    /** Kuartil dengan metode interpolasi linear (setara pandas default). */
    public static function quantile(array $sorted, float $q): float
    {
        $n = count($sorted);
        if ($n === 0) {
            return 0.0;
        }
        if ($n === 1) {
            return (float) $sorted[0];
        }

        $pos = ($n - 1) * $q;
        $lower = (int) floor($pos);
        $upper = (int) ceil($pos);
        if ($lower === $upper) {
            return (float) $sorted[$lower];
        }

        $frac = $pos - $lower;

        return (float) $sorted[$lower] * (1 - $frac) + (float) $sorted[$upper] * $frac;
    }

    public static function stdDev(array $vals, ?float $mean = null): float
    {
        $n = count($vals);
        if ($n < 2) {
            return 0.0;
        }
        $mean ??= array_sum($vals) / $n;
        $sq = 0.0;
        foreach ($vals as $v) {
            $sq += ($v - $mean) ** 2;
        }

        // Sampel (n-1), sama seperti pandas default
        return sqrt($sq / ($n - 1));
    }

    /**
     * Skewness momen ketiga (setara pandas Series.skew()).
     */
    public static function skewness(array $vals): float
    {
        $n = count($vals);
        if ($n < 3) {
            return 0.0;
        }
        $mean = array_sum($vals) / $n;
        $m2 = 0.0;
        $m3 = 0.0;
        foreach ($vals as $v) {
            $d = $v - $mean;
            $m2 += $d ** 2;
            $m3 += $d ** 3;
        }
        $m2 /= $n;
        $m3 /= $n;
        if ($m2 <= 0.0) {
            return 0.0;
        }

        // Koreksi bias sampel
        $g1 = $m3 / ($m2 ** 1.5);

        return $g1 * sqrt($n * ($n - 1)) / ($n - 2);
    }

    /**
     * Hitung frekuensi nilai, urut dari terbanyak.
     *
     * @return array<string, int>
     */
    public function valueCounts(string $column, ?int $limit = null): array
    {
        $counts = [];
        foreach ($this->column($column) as $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $key = is_scalar($v) ? (string) $v : json_encode($v);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        arsort($counts);

        return $limit ? array_slice($counts, 0, $limit, true) : $counts;
    }

    /**
     * Groupby + agregasi.
     *
     * @param array<int, string> $groupColumns
     * @param array<string, string> $aggregations ['kolom' => 'sum'|'mean'|'count'|'min'|'max']
     * @return array<int, array<string, mixed>>
     */
    public function groupBy(array $groupColumns, array $aggregations): array
    {
        $groups = [];

        foreach ($this->rows as $row) {
            $keyParts = [];
            foreach ($groupColumns as $gc) {
                $keyParts[] = (string) ($row[$gc] ?? '(kosong)');
            }
            $key = implode("\x1F", $keyParts);

            if (!isset($groups[$key])) {
                $groups[$key] = ['labels' => $keyParts, 'rows' => 0, 'acc' => []];
                foreach ($aggregations as $col => $_) {
                    $groups[$key]['acc'][$col] = [];
                }
            }

            $groups[$key]['rows']++;
            foreach ($aggregations as $col => $_) {
                $n = self::tryNumeric($row[$col] ?? null);
                if ($n !== null) {
                    $groups[$key]['acc'][$col][] = (float) $n;
                }
            }
        }

        $result = [];
        foreach ($groups as $group) {
            $entry = [];
            foreach ($groupColumns as $i => $gc) {
                $entry[$gc] = $group['labels'][$i];
            }
            $entry['_count'] = $group['rows'];

            foreach ($aggregations as $col => $agg) {
                $vals = $group['acc'][$col];
                $entry[$col . '_' . $agg] = match ($agg) {
                    'count' => count($vals),
                    'sum' => array_sum($vals),
                    'mean' => $vals ? array_sum($vals) / count($vals) : 0.0,
                    'min' => $vals ? min($vals) : 0.0,
                    'max' => $vals ? max($vals) : 0.0,
                    default => 0.0,
                };
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * Koefisien korelasi Pearson antar dua kolom numerik.
     */
    public function correlation(string $a, string $b): ?float
    {
        $pairs = [];
        foreach ($this->rows as $row) {
            $x = self::tryNumeric($row[$a] ?? null);
            $y = self::tryNumeric($row[$b] ?? null);
            if ($x !== null && $y !== null) {
                $pairs[] = [(float) $x, (float) $y];
            }
        }

        $n = count($pairs);
        if ($n < 2) {
            return null;
        }

        $sumX = $sumY = $sumXY = $sumX2 = $sumY2 = 0.0;
        foreach ($pairs as [$x, $y]) {
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
            $sumY2 += $y * $y;
        }

        $num = $n * $sumXY - $sumX * $sumY;
        $den = sqrt(($n * $sumX2 - $sumX ** 2) * ($n * $sumY2 - $sumY ** 2));

        return $den == 0.0 ? null : $num / $den;
    }

    // ───────────────────────── Mutasi ─────────────────────────

    /**
     * Ganti nama kolom. $mapping = ['nama_lama' => 'nama_baru'].
     */
    public function renameColumns(array $mapping): void
    {
        if ($mapping === []) {
            return;
        }

        $columns = [];
        foreach ($this->columns as $col) {
            $columns[] = $mapping[$col] ?? $col;
        }

        foreach ($this->rows as $i => $row) {
            $newRow = [];
            foreach ($this->columns as $col) {
                $newRow[$mapping[$col] ?? $col] = $row[$col] ?? null;
            }
            $this->rows[$i] = $newRow;
        }

        $this->columns = $columns;
        $this->detectTypes();
    }

    /**
     * Ubah nilai satu kolom untuk semua baris.
     *
     * @param callable(mixed, array<string, mixed>): mixed $fn
     */
    public function mapColumn(string $column, callable $fn): void
    {
        foreach ($this->rows as $i => $row) {
            $this->rows[$i][$column] = $fn($row[$column] ?? null, $row);
        }
        $this->detectTypes();
    }

    /**
     * Hapus baris yang semua kolomnya null.
     *
     * @return int jumlah baris terhapus
     */
    public function dropEmptyRows(): int
    {
        $before = count($this->rows);
        $this->rows = array_values(array_filter($this->rows, function ($row) {
            foreach ($row as $v) {
                if ($v !== null && $v !== '') {
                    return true;
                }
            }

            return false;
        }));

        return $before - count($this->rows);
    }

    /**
     * Hapus baris duplikat identik.
     *
     * @return int jumlah baris terhapus
     */
    public function dropDuplicates(): int
    {
        $before = count($this->rows);
        $seen = [];
        $unique = [];

        foreach ($this->rows as $row) {
            $key = json_encode($row);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $row;
        }

        $this->rows = $unique;

        return $before - count($unique);
    }

    /**
     * Isi nilai null pada kolom numerik dengan median, kolom lain dengan konstanta.
     *
     * @return array<string, array<string, mixed>> detail per kolom
     */
    public function fillNulls(string $constant = 'Tidak diketahui'): array
    {
        $details = [];

        foreach ($this->columns as $col) {
            $nullCount = 0;
            foreach ($this->rows as $row) {
                $v = $row[$col] ?? null;
                if ($v === null || $v === '') {
                    $nullCount++;
                }
            }
            if ($nullCount === 0) {
                continue;
            }

            if ($this->type($col) === 'numeric') {
                $vals = $this->numericColumn($col);
                sort($vals);
                $median = self::median($vals);
                foreach ($this->rows as $i => $row) {
                    $v = $row[$col] ?? null;
                    if ($v === null || $v === '') {
                        $this->rows[$i][$col] = $median;
                    }
                }
                $details[$col] = ['method' => 'median', 'value' => $median, 'count' => $nullCount];
            } else {
                foreach ($this->rows as $i => $row) {
                    $v = $row[$col] ?? null;
                    if ($v === null || $v === '') {
                        $this->rows[$i][$col] = $constant;
                    }
                }
                $details[$col] = ['method' => 'constant', 'value' => $constant, 'count' => $nullCount];
            }
        }

        $this->detectTypes();

        return $details;
    }

    /**
     * Simpan sebagai CSV (untuk file hasil cleaning).
     */
    public function toCsv(string $path): void
    {
        $handle = fopen($path, 'wb');
        if (!$handle) {
            throw new \RuntimeException("Tidak bisa menulis file: {$path}");
        }

        fputcsv($handle, $this->columns, ',', '"', '');
        foreach ($this->rows as $row) {
            $line = [];
            foreach ($this->columns as $col) {
                $line[] = $row[$col] ?? null;
            }
            fputcsv($handle, $line, ',', '"', '');
        }

        fclose($handle);
    }

    /**
     * Estimasi memori terpakai (setara memory_usage pada pandas).
     */
    public function memoryUsageMb(): float
    {
        return round(strlen(serialize($this->rows)) / (1024 * 1024), 2);
    }
}
