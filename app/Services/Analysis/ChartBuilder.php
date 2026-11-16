<?php

namespace App\Services\Analysis;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ChartBuilder — port PHP dari python-service/chart_generator.py
 *
 * Menghasilkan 8 chart adaptif sebagai PNG memakai GD, ditulis langsung ke
 * storage/app/public/charts/{report_id}/. Karena pipeline kini berjalan di
 * dalam Laravel (tanpa Python), tidak ada lagi transfer base64 antar container.
 *
 * Palet kategorik diambil dari palet tervalidasi (buta warna aman pada
 * urutan slotnya). Warna tunggal memakai navy brand proyek.
 */
class ChartBuilder
{
    /** Warna tunggal (brand navy) — kontras tinggi di atas permukaan terang. */
    private const SINGLE_COLOR = '#1B4F72';

    /** Warna aksen untuk anotasi puncak / garis median. */
    private const ACCENT_COLOR = '#EB6834';

    /**
     * Palet kategorik 8 slot, urutan tetap (jangan diacak).
     * Urutan inilah mekanisme keamanan buta warna — ΔE CVD antar slot
     * bersebelahan ≥ 9.
     */
    private const SERIES = [
        '#2A78D6', // biru
        '#EB6834', // oranye
        '#1BAF7A', // aqua
        '#EDA100', // kuning
        '#E87BA4', // magenta
        '#008300', // hijau
        '#6250D6', // violet
        '#E34948', // merah
    ];

    private const BG_COLOR = '#FAFBFC';

    private const PLOT_BG = '#FFFFFF';

    private const INK_PRIMARY = '#0B0B0B';

    private const INK_SECONDARY = '#52514E';

    private const INK_MUTED = '#898781';

    private const GRID_COLOR = '#E1E0D9';

    private const AXIS_COLOR = '#C3C2B7';

    private const WIDTH = 1200;

    private const HEIGHT = 620;

    /** Margin plot standar. */
    private const M_LEFT = 96;

    private const M_RIGHT = 44;

    private const M_TOP = 104;

    private const M_BOTTOM = 124;

    /** Report yang sedang diproses — dipakai saat menamai file chart. */
    private int $reportId = 0;

    /**
     * Bangun semua chart yang relevan untuk dataset.
     *
     * @param array<string, mixed> $stats keluaran DataAnalyzer (dipakai untuk korelasi)
     * @return array<int, array{path: string, filename: string, width: int, height: int}>
     */
    public function run(DataTable $table, array $stats, int $reportId): array
    {
        $this->reportId = $reportId;

        $folder = $this->folder($reportId);
        if (!is_dir($folder) && !mkdir($folder, 0o755, true) && !is_dir($folder)) {
            throw new \RuntimeException("Tidak bisa membuat folder chart: {$folder}");
        }

        $numCols = $this->measureColumns($table);
        $dateCols = $this->detectDateColumns($table);

        $allCat = [];
        foreach ($table->stringColumns() as $col) {
            if ($table->columnSummary($col)['unique'] <= 20 && $table->columnSummary($col)['unique'] > 1) {
                $allCat[] = $col;
            }
        }
        // Kolom nama/label didahulukan dibanding kolom ID/kode agar chart terbaca
        usort($allCat, fn ($a, $b) => $this->isIdColumn($a) <=> $this->isIdColumn($b));

        $catCols = $allCat;
        $catSmall = array_values(array_filter($catCols, fn ($c) => $table->columnSummary($c)['unique'] <= 8));

        $charts = [];

        $attempts = [
            fn (int $idx) => $dateCols !== [] && $numCols !== []
                ? $this->chartTrendLine($table, $dateCols[0], $numCols[0], $idx)
                : null,
            fn (int $idx) => $catCols !== [] && $numCols !== []
                ? $this->chartCategoryBar($table, $catCols[0], $numCols[0], $idx)
                : null,
            fn (int $idx) => count($catCols) >= 2 && $numCols !== []
                ? $this->chartHorizontalBar($table, $catCols[1], $numCols[0], $idx)
                : null,
            fn (int $idx) => $catSmall !== []
                ? $this->chartDonut($table, $catSmall[0], $numCols[0] ?? null, $idx)
                : null,
            fn (int $idx) => count($catCols) >= 2 && $numCols !== []
                ? $this->chartHeatmap($table, $catCols[0], $catCols[1], $numCols[0], $idx)
                : null,
            fn (int $idx) => $catSmall !== [] && $numCols !== []
                ? $this->chartBoxplot($table, $catSmall[0], $numCols[0], $idx)
                : null,
            fn (int $idx) => $numCols !== []
                ? $this->chartHistogram($table, $numCols[0], $idx)
                : null,
            fn (int $idx) => count($numCols) >= 2
                ? $this->chartScatter($table, $numCols, $catCols, $idx)
                : null,
        ];

        foreach ($attempts as $attempt) {
            $result = null;
            try {
                $result = $attempt(count($charts));
            } catch (\Throwable $e) {
                Log::warning('Chart generation gagal: ' . $e->getMessage());
            }

            if ($result !== null) {
                $charts[] = $result;
            }
        }

        Log::info('Chart dibuat', ['report_id' => $reportId, 'count' => count($charts)]);

        return $charts;
    }

    // ───────────────────────── Helper umum ─────────────────────────

    private function folder(int $reportId): string
    {
        return Storage::disk('public')->path('charts/' . $reportId);
    }

    /**
     * Simpan canvas ke path relatif terhadap disk public.
     *
     * @return array{path: string, filename: string, width: int, height: int}
     */
    private function save(ChartCanvas $canvas, int $idx, string $name): array
    {
        $filename = ($idx + 1) . '_' . $name . '.png';
        $canvas->save($this->folder($this->reportId) . DIRECTORY_SEPARATOR . $filename);

        return [
            'path' => 'charts/' . $this->reportId . '/' . $filename,
            'filename' => $filename,
            'width' => $canvas->w,
            'height' => $canvas->h,
        ];
    }

    private function isIdColumn(string $col): int
    {
        $lower = strtolower($col);
        foreach (['_id', '_code', '_no', '_num', '_number', 'id', 'code'] as $suffix) {
            if (str_ends_with($lower, $suffix)) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * Kolom numerik yang layak dijadikan ukuran chart.
     *
     * Kolom ID/kode dan kolom nomor urut (mis. order_id, No) dibuang:
     * menjumlahkannya menghasilkan angka tanpa makna bisnis.
     *
     * @return array<int, string>
     */
    private function measureColumns(DataTable $table): array
    {
        $measures = [];
        $rowCount = $table->count();

        foreach ($table->numericColumns() as $col) {
            if ($this->isIdColumn($col) === 1) {
                continue;
            }

            $vals = $table->numericColumn($col);
            if ($vals === []) {
                continue;
            }

            // Nomor urut: semua nilai unik, bulat, dan jumlahnya = jumlah baris
            $unique = count(array_unique($vals));
            $allIntegers = count(array_filter($vals, fn ($v) => floor($v) === $v)) === count($vals);
            if ($unique === count($vals) && $unique === $rowCount && $allIntegers) {
                continue;
            }

            // Kolom konstan juga tidak berguna sebagai ukuran
            if ($unique <= 1) {
                continue;
            }

            $measures[] = $col;
        }

        // Kolom dengan total terbesar lebih dulu: biasanya kolom nilai uang
        // (total/revenue) yang jauh lebih informatif daripada harga satuan.
        usort($measures, function ($a, $b) use ($table) {
            return array_sum($table->numericColumn($b)) <=> array_sum($table->numericColumn($a));
        });

        return $measures;
    }

    /**
     * Kolom yang mayoritas nilainya bisa di-parse sebagai tanggal.
     *
     * @return array<int, string>
     */
    private function detectDateColumns(DataTable $table): array
    {
        $out = [];

        foreach ($table->columns() as $col) {
            if ($table->type($col) === 'numeric') {
                continue;
            }

            $vals = $table->column($col);
            $total = 0;
            $parsed = 0;

            foreach ($vals as $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                $total++;
                if ($total > 50 && $parsed / $total > 0.9) {
                    // sampel awal sudah cukup meyakinkan
                }
                if (DataTable::isDateLike((string) $v)) {
                    $parsed++;
                }
                if ($total >= 200) {
                    break;
                }
            }

            if ($total > 0 && $parsed / $total > 0.8) {
                $out[] = $col;
            }
        }

        return $out;
    }

    /**
     * Format ringkas ala Indonesia: 1,2 jt / 850 rb.
     */
    private function formatCompact(float $v): string
    {
        $abs = abs($v);

        if ($abs >= 1e12) {
            return $this->decimal($v / 1e12) . ' T';
        }
        if ($abs >= 1e9) {
            return $this->decimal($v / 1e9) . ' M';
        }
        if ($abs >= 1e6) {
            return $this->decimal($v / 1e6) . ' jt';
        }
        if ($abs >= 1e3) {
            return $this->decimal($v / 1e3) . ' rb';
        }
        if ($abs >= 1) {
            return number_format($v, 0, ',', '.');
        }
        if ($abs > 0) {
            return number_format($v, 2, ',', '.');
        }

        return '0';
    }

    private function decimal(float $v): string
    {
        $decimals = abs($v) >= 10 ? 0 : 1;
        $out = number_format($v, $decimals, ',', '.');
        if ($decimals === 1 && str_ends_with($out, ',0')) {
            $out = substr($out, 0, -2);
        }

        return $out;
    }

    private function formatFull(float $v): string
    {
        return number_format($v, abs($v) < 10 && floor($v) !== $v ? 2 : 0, ',', '.');
    }

    /**
     * Judul chart + garis dasar area plot.
     */
    private function drawTitle(ChartCanvas $c, string $title, ?string $subtitle = null): void
    {
        $c->text(22, self::M_LEFT, 42, $title, self::INK_PRIMARY, 'left', true);
        if ($subtitle !== null && $subtitle !== '') {
            $c->text(13, self::M_LEFT, 66, $subtitle, self::INK_MUTED);
        }
    }

    /**
     * Bingkai plot: latar putih, gridline horizontal, tick sumbu-y.
     *
     * @param array<int, float> $ticks
     */
    private function drawYAxis(ChartCanvas $c, array $ticks, int $left, int $top, int $right, int $bottom): void
    {
        $c->filledRect($left, $top, $right, $bottom, self::PLOT_BG);

        $min = $ticks[0] ?? 0.0;
        $max = end($ticks) ?: $min + 1;

        foreach ($ticks as $tick) {
            $y = $this->scaleY((float) $tick, (float) $min, (float) $max, $top, $bottom);
            $c->line($left, $y, $right, $y, self::GRID_COLOR);
            $c->text(11, $left - 12, $y + 4, $this->formatCompact((float) $tick), self::INK_MUTED, 'right');
        }

        $c->line($left, $bottom, $right, $bottom, self::AXIS_COLOR);
        $c->line($left, $top, $left, $bottom, self::AXIS_COLOR);
    }

    private function scaleY(float $value, float $min, float $max, int $top, int $bottom): float
    {
        if ($max <= $min) {
            return ($top + $bottom) / 2;
        }

        return $bottom - ($value - $min) / ($max - $min) * ($bottom - $top);
    }

    /**
     * Skala sumbu-y dengan baseline 0 (untuk bar).
     *
     * @param array<int, float> $values
     * @return array{0: array<int, float>, 1: float, 2: float}
     */
    private function yTicksFromValues(array $values): array
    {
        if ($values === []) {
            return [[0.0, 1.0], 0.0, 1.0];
        }

        $min = min($values);
        $max = max($values);

        // Bar selalu mulai dari 0 agar panjang batang proporsional
        $min = min(0.0, $min);
        if ($max <= 0) {
            $max = $min + 1;
        }

        $ticks = ChartCanvas::ticks($min, $max, 5);
        if ($ticks === []) {
            $ticks = [0.0, 1.0];
        }

        return [$ticks, (float) $ticks[0], (float) end($ticks)];
    }

    // ── Chart 1: Tren bulanan + area fill ──
    private function chartTrendLine(DataTable $table, string $dateCol, string $valCol, int $idx): ?array
    {
        $monthly = [];

        foreach ($table->rows() as $row) {
            $raw = $row[$dateCol] ?? null;
            $val = DataTable::tryNumeric($row[$valCol] ?? null);
            if ($raw === null || $val === null) {
                continue;
            }

            $ts = is_numeric($raw) ? (int) $raw : strtotime((string) $raw);
            if (!$ts) {
                continue;
            }

            $key = date('Y-m', $ts);
            $monthly[$key] = ($monthly[$key] ?? 0.0) + (float) $val;
        }

        if (count($monthly) < 3) {
            return null;
        }

        ksort($monthly);
        $labels = array_keys($monthly);
        $values = array_values($monthly);

        $c = new ChartCanvas(self::WIDTH, self::HEIGHT, self::BG_COLOR);
        $this->drawTitle($c, "Tren {$valCol} Bulanan", 'Akumulasi nilai per bulan dari kolom ' . $dateCol);

        [$ticks, $min, $max] = $this->yTicksFromValues($values);
        $left = self::M_LEFT;
        $right = self::WIDTH - self::M_RIGHT;
        $top = self::M_TOP;
        $bottom = self::HEIGHT - self::M_BOTTOM;

        $this->drawYAxis($c, $ticks, $left, $top, $right, $bottom);

        $n = count($values);
        $stepX = $n > 1 ? ($right - $left) / ($n - 1) : 0.0;
        $points = [];
        foreach ($values as $i => $v) {
            $points[] = [$left + $stepX * $i, $this->scaleY((float) $v, $min, $max, $top, $bottom)];
        }

        // Area fill di bawah garis (alpha rendah)
        $area = $points;
        $area[] = [$points[$n - 1][0], $bottom];
        $area[] = [$points[0][0], $bottom];
        $c->filledPolygon($area, self::SINGLE_COLOR, 108);

        foreach ($points as $i => $p) {
            if ($i > 0) {
                $c->line($points[$i - 1][0], $points[$i - 1][1], $p[0], $p[1], self::SINGLE_COLOR, 2);
            }
        }

        // Penanda titik hanya bila bulan tidak terlalu banyak
        if ($n <= 40) {
            foreach ($points as $p) {
                $c->dot($p[0], $p[1], 3, self::SINGLE_COLOR);
            }
        }

        // Anotasi puncak
        $peakIdx = 0;
        foreach ($values as $i => $v) {
            if ($v > $values[$peakIdx]) {
                $peakIdx = $i;
            }
        }
        [$px, $py] = $points[$peakIdx];
        $c->ring($px, $py, 7, self::ACCENT_COLOR);
        $c->dot($px, $py, 4, self::ACCENT_COLOR);

        $peakLabel = 'Puncak: ' . $this->formatCompact((float) $values[$peakIdx]);
        $labelX = min(max($px, $left + 90), $right - 90);
        // Letakkan label di atas titik; jika tidak muat, pindahkan ke bawah titik
        $labelY = $py - 16;
        if ($labelY < $top + 16) {
            $labelY = $py + 26;
        }
        $c->text(11, $labelX, $labelY, $peakLabel, self::ACCENT_COLOR, 'center', true);

        // Label sumbu-x: tampilkan tiap label ke-n agar tidak bertumpuk
        $labelEvery = max(1, (int) ceil($n / 12));
        foreach ($labels as $i => $label) {
            if ($i % $labelEvery !== 0 && $i !== $n - 1) {
                continue;
            }
            $c->rotatedLabel(10, $points[$i][0], $bottom + 10, $label, self::INK_MUTED, 110);
        }

        return $this->save($c, $idx, 'tren_bulanan');
    }

    // ── Chart 2: Bar vertikal top-10 kategori ──
    private function chartCategoryBar(DataTable $table, string $catCol, string $valCol, int $idx): ?array
    {
        $groups = $table->groupBy([$catCol], [$valCol => 'sum']);
        usort($groups, fn ($a, $b) => $b[$valCol . '_sum'] <=> $a[$valCol . '_sum']);
        $groups = array_slice($groups, 0, 10);

        if ($groups === []) {
            return null;
        }

        $labels = array_map(fn ($g) => (string) $g[$catCol], $groups);
        $values = array_map(fn ($g) => (float) $g[$valCol . '_sum'], $groups);

        $c = new ChartCanvas(self::WIDTH, self::HEIGHT, self::BG_COLOR);
        $this->drawTitle($c, "{$valCol} per {$catCol}", 'Total 10 kategori teratas');

        [$ticks, $min, $max] = $this->yTicksFromValues($values);
        $left = self::M_LEFT;
        $right = self::WIDTH - self::M_RIGHT;
        $top = self::M_TOP;
        $bottom = self::HEIGHT - self::M_BOTTOM;

        $this->drawYAxis($c, $ticks, $left, $top, $right, $bottom);

        $n = count($values);
        $slot = ($right - $left) / max($n, 1);
        $barW = min($slot * 0.62, 64.0);

        foreach ($values as $i => $v) {
            $centerX = $left + $slot * ($i + 0.5);
            $yTop = $this->scaleY($v, $min, $max, $top, $bottom);

            $c->roundedRect(
                $centerX - $barW / 2,
                $yTop,
                $centerX + $barW / 2,
                $bottom - 1,
                4,
                self::SERIES[$i % count(self::SERIES)]
            );

            // Label nilai langsung di atas batang (relief untuk kontras)
            $c->textTruncated(
                11,
                $centerX,
                $yTop - 9,
                $this->formatCompact($v),
                self::INK_SECONDARY,
                $slot - 4,
                'center',
                true
            );

            $c->rotatedLabel(10, $centerX, $bottom + 10, $labels[$i], self::INK_MUTED, 140);
        }

        return $this->save($c, $idx, 'bar_kategori');
    }

    // ── Chart 3: Bar horizontal top-10 ──
    private function chartHorizontalBar(DataTable $table, string $catCol, string $valCol, int $idx): ?array
    {
        $groups = $table->groupBy([$catCol], [$valCol => 'sum']);
        usort($groups, fn ($a, $b) => $b[$valCol . '_sum'] <=> $a[$valCol . '_sum']);
        $groups = array_slice($groups, 0, 10);

        if (count($groups) < 2) {
            return null;
        }

        // Urutan terbesar di atas
        $labels = array_map(fn ($g) => (string) $g[$catCol], $groups);
        $values = array_map(fn ($g) => (float) $g[$valCol . '_sum'], $groups);

        $height = max(self::HEIGHT, 120 + count($values) * 44);
        $c = new ChartCanvas(self::WIDTH, $height, self::BG_COLOR);

        $left = 250;
        $right = self::WIDTH - 130;
        $top = self::M_TOP;
        $bottom = $height - 70;

        $this->drawTitle($c, "Top {$catCol} menurut {$valCol}", '10 kategori teratas, total nilai');

        $c->filledRect($left, $top, $right, $bottom, self::PLOT_BG);
        $c->line($left, $top, $left, $bottom, self::AXIS_COLOR);

        $maxVal = max($values) ?: 1.0;
        $n = count($values);
        $slot = ($bottom - $top) / max($n, 1);
        $barH = min($slot * 0.56, 26.0);

        foreach ($values as $i => $v) {
            $centerY = $top + $slot * ($i + 0.5);
            $barW = ($v / $maxVal) * ($right - $left);

            $c->roundedRect(
                $left + 1,
                $centerY - $barH / 2,
                $left + max($barW, 2),
                $centerY + $barH / 2,
                4,
                self::SERIES[$i % count(self::SERIES)]
            );

            $c->textTruncated(12, $left - 14, $centerY + 4, $labels[$i], self::INK_SECONDARY, $left - 30, 'right');
            $c->text(11, $left + $barW + 10, $centerY + 4, $this->formatCompact($v), self::INK_SECONDARY, 'left', true);
        }

        // Gridline vertikal tipis
        foreach (ChartCanvas::ticks(0, $maxVal, 5) as $tick) {
            $x = $left + ($tick / max($maxVal, 1e-9)) * ($right - $left);
            if ($x > $left && $x < $right) {
                $c->line($x, $top, $x, $bottom, self::GRID_COLOR);
                $c->text(10, $x, $bottom + 20, $this->formatCompact((float) $tick), self::INK_MUTED, 'center');
            }
        }

        return $this->save($c, $idx, 'bar_horizontal');
    }

    // ── Chart 4: Donut proporsi ──
    private function chartDonut(DataTable $table, string $catCol, ?string $valCol, int $idx): ?array
    {
        if ($valCol !== null) {
            $groups = $table->groupBy([$catCol], [$valCol => 'sum']);
            usort($groups, fn ($a, $b) => $b[$valCol . '_sum'] <=> $a[$valCol . '_sum']);
            $labels = array_map(fn ($g) => (string) $g[$catCol], $groups);
            $values = array_map(fn ($g) => abs((float) $g[$valCol . '_sum']), $groups);
        } else {
            $counts = $table->valueCounts($catCol, 8);
            $labels = array_keys($counts);
            $values = array_map('floatval', array_values($counts));
        }

        // Gabungkan sisa kategori menjadi "Lainnya" agar maksimal 8 wedge
        if (count($values) > 8) {
            $rest = array_slice($values, 7);
            $values = array_slice($values, 0, 7);
            $labels = array_slice($labels, 0, 7);
            $values[] = array_sum($rest);
            $labels[] = 'Lainnya';
        }

        if (count($values) < 2 || array_sum($values) <= 0) {
            return null;
        }

        $c = new ChartCanvas(self::WIDTH, self::HEIGHT, self::BG_COLOR);
        $this->drawTitle($c, "Proporsi {$catCol}", 'Perbandingan kontribusi antar kategori');

        $cx = 330;
        $cy = 340;
        $outer = 190;
        $inner = 116;

        $total = array_sum($values);
        $start = -90.0;

        foreach ($values as $i => $v) {
            $sweep = ($v / $total) * 360.0;
            $end = $start + $sweep;

            $c->donutSlice($cx, $cy, $outer, $inner, $start, $end, self::SERIES[$i % count(self::SERIES)]);

            $start = $end;
        }

        // Label persentase di tengah tiap wedge (hanya wedge yang cukup besar)
        $start = -90.0;
        foreach ($values as $i => $v) {
            $sweep = ($v / $total) * 360.0;
            $mid = deg2rad($start + $sweep / 2);
            $start += $sweep;

            if ($sweep < 12) {
                continue;
            }

            $r = ($outer + $inner) / 2;
            $lx = $cx + cos($mid) * $r;
            $ly = $cy + sin($mid) * $r;
            $pct = $v / $total * 100;

            $color = self::SERIES[$i % count(self::SERIES)];
            $textColor = ChartCanvas::isLightColor($color) ? self::INK_PRIMARY : '#FFFFFF';
            $c->text(12, $lx, $ly + 4, number_format($pct, 1, ',', '.') . '%', $textColor, 'center', true);
        }

        // Total di tengah donut
        $c->text(12, $cx, $cy - 14, 'Total', self::INK_MUTED, 'center');
        $c->text(20, $cx, $cy + 14, $this->formatCompact($total), self::INK_PRIMARY, 'center', true);

        // Legenda di kanan
        $legendX = 660;
        $legendY = 150;
        $rowH = 30;

        foreach ($labels as $i => $label) {
            $color = self::SERIES[$i % count(self::SERIES)];
            $y = $legendY + $i * $rowH;

            $c->roundedRect($legendX, $y - 9, $legendX + 12, $y + 3, 3, $color);
            $c->textTruncated(
                12,
                $legendX + 22,
                $y + 3,
                $label,
                self::INK_SECONDARY,
                self::WIDTH - self::M_RIGHT - $legendX - 120
            );
            $c->text(
                12,
                self::WIDTH - self::M_RIGHT,
                $y + 3,
                number_format($values[$i] / $total * 100, 1, ',', '.') . '%',
                self::INK_MUTED,
                'right'
            );
        }

        return $this->save($c, $idx, 'donut_proporsi');
    }

    private function isLight(string $hex): bool
    {
        return ChartCanvas::isLightColor($hex);
    }

    // ── Chart 5: Heatmap 2 kategori × 1 numerik ──
    private function chartHeatmap(
        DataTable $table,
        string $cat1,
        string $cat2,
        string $valCol,
        int $idx
    ): ?array {
        $u1 = $table->columnSummary($cat1)['unique'];
        $u2 = $table->columnSummary($cat2)['unique'];

        if ($u1 > 10 || $u2 > 10 || $u1 < 2 || $u2 < 2) {
            return null;
        }

        // Pivot: index = cat1, kolom = cat2
        $rowLabels = [];
        $colLabels = [];
        $cells = [];

        foreach ($table->rows() as $row) {
            $r = (string) ($row[$cat1] ?? '(kosong)');
            $k = (string) ($row[$cat2] ?? '(kosong)');
            $v = DataTable::tryNumeric($row[$valCol] ?? null);

            $rowLabels[$r] = true;
            $colLabels[$k] = true;

            if ($v !== null) {
                $cells[$r][$k] = ($cells[$r][$k] ?? 0.0) + (float) $v;
            } else {
                $cells[$r][$k] ??= 0.0;
            }
        }

        $rowLabels = array_keys($rowLabels);
        $colLabels = array_keys($colLabels);

        if ($rowLabels === [] || $colLabels === []) {
            return null;
        }

        // Dua kolom yang saling bergantung (mis. produk × kategori, tiap produk
        // hanya punya 1 kategori) menghasilkan heatmap hampir diagonal yang
        // tidak informatif. Lewati jika sel terisi < 40% dari grid.
        $nonEmpty = 0;
        foreach ($rowLabels as $r) {
            foreach ($colLabels as $k) {
                if (($cells[$r][$k] ?? 0.0) > 0) {
                    $nonEmpty++;
                }
            }
        }
        $fillRate = $nonEmpty / max(count($rowLabels) * count($colLabels), 1);
        if ($fillRate < 0.4) {
            return null;
        }

        // Urutkan berdasarkan total agar mudah dibaca
        $rowTotals = [];
        foreach ($rowLabels as $r) {
            $rowTotals[$r] = array_sum($cells[$r] ?? []);
        }
        arsort($rowTotals);
        $rowLabels = array_keys($rowTotals);

        $colTotals = [];
        foreach ($colLabels as $k) {
            $sum = 0.0;
            foreach ($rowLabels as $r) {
                $sum += $cells[$r][$k] ?? 0.0;
            }
            $colTotals[$k] = $sum;
        }
        arsort($colTotals);
        $colLabels = array_keys($colTotals);

        $heatHeight = max(self::HEIGHT, 150 + count($rowLabels) * 52);
        $c = new ChartCanvas(self::WIDTH, $heatHeight, self::BG_COLOR);
        $this->drawTitle($c, "Heatmap: {$cat1} × {$cat2}", 'Total ' . $valCol . ' per kombinasi kategori');

        $gridLeft = 260;
        // Label kolom diputar 45° ke arah atas — sisakan ruang agar tidak
        // menabrak judul.
        $gridTop = 176;
        $cellW = min(110.0, (self::WIDTH - self::M_RIGHT - $gridLeft) / max(count($colLabels), 1));
        $cellH = 48.0;
        $gridBottom = $gridTop + $cellH * count($rowLabels);
        $gridRight = $gridLeft + $cellW * count($colLabels);

        $maxCell = 0.0;
        foreach ($cells as $row) {
            foreach ($row as $v) {
                $maxCell = max($maxCell, (float) $v);
            }
        }
        $maxCell = $maxCell ?: 1.0;

        // Label kolom (rotasi 45°)
        foreach ($colLabels as $j => $colLabel) {
            $x = $gridLeft + $cellW * ($j + 0.5);
            $c->rotatedLabel(11, $x, $gridTop - 8, $colLabel, self::INK_SECONDARY, 130);
        }

        // Label baris + sel
        foreach ($rowLabels as $i => $rowLabel) {
            $y = $gridTop + $cellH * $i;

            $c->textTruncated(12, $gridLeft - 14, $y + $cellH / 2 + 4, $rowLabel, self::INK_SECONDARY, $gridLeft - 30, 'right');

            foreach ($colLabels as $j => $colLabel) {
                $x = $gridLeft + $cellW * $j;
                $value = (float) ($cells[$rowLabel][$colLabel] ?? 0.0);

                // Ramp sequential satu hue: biru muda → biru tua
                $t = $maxCell > 0 ? min(1.0, $value / $maxCell) : 0.0;
                $fill = $this->seqBlue($t);

                $c->filledRect($x + 1, $y + 1, $x + $cellW - 1, $y + $cellH - 1, $fill);

                $textColor = $t > 0.55 ? '#FFFFFF' : self::INK_PRIMARY;
                $c->text(
                    11,
                    $x + $cellW / 2,
                    $y + $cellH / 2 + 4,
                    $this->formatCompact($value),
                    $textColor,
                    'center',
                    $t > 0.55
                );
            }
        }

        // Bingkai grid
        $c->rect($gridLeft, $gridTop, $gridRight, $gridBottom, self::AXIS_COLOR);

        // Legenda ramp
        $legendY = $gridBottom + 40;
        $c->text(11, $gridLeft, $legendY, 'Rendah', self::INK_MUTED);
        $c->text(11, $gridLeft + 260, $legendY, 'Tinggi', self::INK_MUTED);

        for ($i = 0; $i <= 40; $i++) {
            $x = $gridLeft + 62 + $i * 4.5;
            $c->filledRect($x, $legendY - 13, $x + 4.5, $legendY - 1, $this->seqBlue($i / 40));
        }
        $c->text(11, $gridLeft, $legendY + 18, $this->formatFull(0), self::INK_MUTED);
        $c->text(11, $gridLeft + 322, $legendY + 18, $this->formatCompact($maxCell), self::INK_MUTED, 'right');

        return $this->save($c, $idx, 'heatmap');
    }

    /**
     * Ramp sequential satu hue (biru): terang → gelap.
     */
    private function seqBlue(float $t): string
    {
        $t = max(0.0, min(1.0, $t));
        [$r1, $g1, $b1] = [0xEE, 0xF4, 0xFA];
        [$r2, $g2, $b2] = [0x1B, 0x4F, 0x72];

        return sprintf(
            '#%02X%02X%02X',
            (int) round($r1 + ($r2 - $r1) * $t),
            (int) round($g1 + ($g2 - $g1) * $t),
            (int) round($b1 + ($b2 - $b1) * $t)
        );
    }

    // ── Chart 6: Boxplot per kategori ──
    private function chartBoxplot(DataTable $table, string $catCol, string $valCol, int $idx): ?array
    {
        $counts = $table->valueCounts($catCol, 6);
        $cats = array_keys($counts);

        if (count($cats) < 2) {
            return null;
        }

        $series = [];
        foreach ($cats as $cat) {
            $vals = [];
            foreach ($table->rows() as $row) {
                if ((string) ($row[$catCol] ?? '') !== $cat) {
                    continue;
                }
                $v = DataTable::tryNumeric($row[$valCol] ?? null);
                if ($v !== null) {
                    $vals[] = (float) $v;
                }
            }
            if (count($vals) >= 3) {
                sort($vals);
                $series[$cat] = $vals;
            }
        }

        if ($series === []) {
            return null;
        }

        $all = [];
        foreach ($series as $vals) {
            foreach ($vals as $v) {
                $all[] = $v;
            }
        }

        $c = new ChartCanvas(self::WIDTH, self::HEIGHT, self::BG_COLOR);
        $this->drawTitle($c, "Distribusi {$valCol} per {$catCol}", 'Kotak = Q1–Q3, garis tengah = median, titik = outlier');

        [$ticks, $min, $max] = $this->yTicksFromValues($all);
        $min = (float) $ticks[0];
        $max = (float) end($ticks);

        $left = self::M_LEFT;
        $right = self::WIDTH - self::M_RIGHT;
        $top = self::M_TOP;
        $bottom = self::HEIGHT - self::M_BOTTOM;

        $this->drawYAxis($c, $ticks, $left, $top, $right, $bottom);

        $n = count($series);
        $slot = ($right - $left) / max($n, 1);
        $boxW = min($slot * 0.42, 64.0);

        $i = 0;
        foreach ($series as $cat => $vals) {
            $centerX = $left + $slot * ($i + 0.5);
            $color = self::SERIES[$i % count(self::SERIES)];

            $q1 = DataTable::quantile($vals, 0.25);
            $q2 = DataTable::median($vals);
            $q3 = DataTable::quantile($vals, 0.75);
            $iqr = $q3 - $q1;

            $lowerFence = $iqr > 0 ? $q1 - 1.5 * $iqr : $q1;
            $upperFence = $iqr > 0 ? $q3 + 1.5 * $iqr : $q3;

            $inliers = array_filter($vals, fn ($v) => $v >= $lowerFence && $v <= $upperFence);
            $outliers = array_filter($vals, fn ($v) => $v < $lowerFence || $v > $upperFence);
            $whiskerLow = $inliers !== [] ? min($inliers) : $q1;
            $whiskerHigh = $inliers !== [] ? max($inliers) : $q3;

            $yQ1 = $this->scaleY($q1, $min, $max, $top, $bottom);
            $yQ3 = $this->scaleY($q3, $min, $max, $top, $bottom);
            $yMed = $this->scaleY($q2, $min, $max, $top, $bottom);
            $yLow = $this->scaleY($whiskerLow, $min, $max, $top, $bottom);
            $yHigh = $this->scaleY($whiskerHigh, $min, $max, $top, $bottom);

            // Whisker
            $c->line($centerX, $yHigh, $centerX, $yQ3, self::AXIS_COLOR, 2);
            $c->line($centerX, $yQ1, $centerX, $yLow, self::AXIS_COLOR, 2);
            $c->line($centerX - $boxW / 3, $yHigh, $centerX + $boxW / 3, $yHigh, self::INK_MUTED, 2);
            $c->line($centerX - $boxW / 3, $yLow, $centerX + $boxW / 3, $yLow, self::INK_MUTED, 2);

            // Kotak
            $c->roundedRect($centerX - $boxW / 2, $yQ3, $centerX + $boxW / 2, $yQ1, 3, $color, 96);
            $c->rect($centerX - $boxW / 2, $yQ3, $centerX + $boxW / 2, $yQ1, $color);

            // Median
            $c->line($centerX - $boxW / 2, $yMed, $centerX + $boxW / 2, $yMed, self::INK_PRIMARY, 3);

            // Outlier
            foreach ($outliers as $o) {
                $c->dot($centerX, $this->scaleY((float) $o, $min, $max, $top, $bottom), 3, $color, 96);
            }

            $c->rotatedLabel(10, $centerX, $bottom + 10, (string) $cat, self::INK_MUTED, 140);
            $i++;
        }

        return $this->save($c, $idx, 'boxplot');
    }

    // ── Chart 7: Histogram + garis mean/median ──
    private function chartHistogram(DataTable $table, string $valCol, int $idx): ?array
    {
        $data = $table->numericColumn($valCol);
        if (count($data) < 5) {
            return null;
        }

        $min = min($data);
        $max = max($data);

        if ($max <= $min) {
            return null;
        }

        $binCount = (int) max(8, min(30, round(sqrt(count($data)) * 1.6)));
        $binWidth = ($max - $min) / $binCount;

        $bins = array_fill(0, $binCount, 0);
        foreach ($data as $v) {
            $i = (int) floor(($v - $min) / $binWidth);
            if ($i >= $binCount) {
                $i = $binCount - 1;
            }
            if ($i < 0) {
                $i = 0;
            }
            $bins[$i]++;
        }

        $c = new ChartCanvas(self::WIDTH, self::HEIGHT, self::BG_COLOR);
        $this->drawTitle($c, "Distribusi {$valCol}", 'Frekuensi nilai dalam ' . $binCount . ' rentang');

        [$ticks, $yMin, $yMax] = $this->yTicksFromValues(array_map('floatval', $bins));
        $left = self::M_LEFT;
        $right = self::WIDTH - self::M_RIGHT;
        $top = self::M_TOP + 14;
        $bottom = self::HEIGHT - self::M_BOTTOM;

        $this->drawYAxis($c, $ticks, $left, $top, $right, $bottom);

        $slot = ($right - $left) / $binCount;
        foreach ($bins as $i => $count) {
            if ($count === 0) {
                continue;
            }
            $x1 = $left + $slot * $i + 1;
            $x2 = $left + $slot * ($i + 1) - 1;
            $yTop = $this->scaleY((float) $count, $yMin, $yMax, $top, $bottom);
            $c->filledRect($x1, $yTop, $x2, $bottom - 1, self::SINGLE_COLOR, 36);
        }

        // Garis median & mean
        $mean = array_sum($data) / count($data);
        sort($data);
        $median = DataTable::median($data);

        $xOf = fn (float $v) => $left + ($v - $min) / ($max - $min) * ($right - $left);

        $medianX = $xOf($median);
        $meanX = $xOf($mean);

        $c->dashedLine($medianX, $top, $medianX, $bottom, self::ACCENT_COLOR, 8, 5);
        $c->dashedLine($meanX, $top, $meanX, $bottom, '#EDA100', 8, 5);

        // Keterangan garis diletakkan di blok tetap kiri-atas: median dan mean
        // sering berimpit (selisih beberapa piksel), label langsung akan
        // saling menimpa.
        $legendTop = $top + 16;
        $legendX = $left + 16;

        $c->dashedLine($legendX, $legendTop, $legendX + 26, $legendTop, self::ACCENT_COLOR, 6, 4);
        $c->text(11, $legendX + 34, $legendTop + 4, 'Median ' . $this->formatCompact($median), self::INK_SECONDARY);

        $c->dashedLine($legendX, $legendTop + 20, $legendX + 26, $legendTop + 20, '#EDA100', 6, 4);
        $c->text(11, $legendX + 34, $legendTop + 24, 'Mean ' . $this->formatCompact($mean), self::INK_SECONDARY);

        // Tick sumbu-x
        foreach (ChartCanvas::ticks($min, $max, 6) as $tick) {
            if ($tick < $min || $tick > $max) {
                continue;
            }
            $x = $xOf((float) $tick);
            $c->line($x, $bottom, $x, $bottom + 4, self::AXIS_COLOR);
            $c->text(10, $x, $bottom + 20, $this->formatCompact((float) $tick), self::INK_MUTED, 'center');
        }

        return $this->save($c, $idx, 'distribusi');
    }

    // ── Chart 8: Scatter korelasi ──
    /**
     * @param array<int, string> $numCols
     * @param array<int, string> $catCols
     */
    private function chartScatter(DataTable $table, array $numCols, array $catCols, int $idx): ?array
    {
        if (count($numCols) < 2) {
            return null;
        }

        $best = null;
        $bestR = 0.0;
        $n = count($numCols);

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $r = $table->correlation($numCols[$i], $numCols[$j]);
                if ($r === null || is_nan($r)) {
                    continue;
                }

                // Dua kolom yang praktis identik (r ≈ 1) hanya menggambar
                // garis diagonal — bukan korelasi yang perlu dilihat.
                if (abs($r) > 0.995) {
                    continue;
                }

                if ($best === null || abs($r) > abs($bestR)) {
                    $best = [$numCols[$i], $numCols[$j]];
                    $bestR = $r;
                }
            }
        }

        if ($best === null) {
            return null;
        }

        [$c1, $c2] = $best;

        // Hue hanya dipakai jika kategori ≤ 3 (batas palet untuk bentuk all-pairs)
        $hueCol = null;
        foreach ($catCols as $cat) {
            $u = $table->columnSummary($cat)['unique'];
            if ($u >= 2 && $u <= 3) {
                $hueCol = $cat;
                break;
            }
        }

        $points = [];
        foreach ($table->rows() as $row) {
            $x = DataTable::tryNumeric($row[$c1] ?? null);
            $y = DataTable::tryNumeric($row[$c2] ?? null);
            if ($x === null || $y === null) {
                continue;
            }
            $hue = $hueCol !== null ? (string) ($row[$hueCol] ?? '(kosong)') : null;
            $points[] = [(float) $x, (float) $y, $hue];
        }

        if (count($points) < 5) {
            return null;
        }

        $xs = array_column($points, 0);
        $ys = array_column($points, 1);

        $c = new ChartCanvas(self::WIDTH, self::HEIGHT, self::BG_COLOR);
        $this->drawTitle(
            $c,
            "Korelasi: {$c1} vs {$c2}",
            'r = ' . number_format($bestR, 2, ',', '.') . ' (' . $this->strengthLabel($bestR) . ')'
        );

        $left = self::M_LEFT;
        $right = self::WIDTH - self::M_RIGHT - ($hueCol !== null ? 150 : 0);
        $top = self::M_TOP + 10;
        $bottom = self::HEIGHT - self::M_BOTTOM;

        $c->filledRect($left, $top, $right, $bottom, self::PLOT_BG);

        $xTicks = ChartCanvas::ticks(min($xs), max($xs), 6);
        $yTicks = ChartCanvas::ticks(min($ys), max($ys), 5);

        $xMin = (float) $xTicks[0];
        $xMax = (float) end($xTicks);
        $yMin = (float) $yTicks[0];
        $yMax = (float) end($yTicks);

        foreach ($yTicks as $tick) {
            $y = $this->scaleY((float) $tick, $yMin, $yMax, $top, $bottom);
            $c->line($left, $y, $right, $y, self::GRID_COLOR);
            $c->text(10, $left - 12, $y + 4, $this->formatCompact((float) $tick), self::INK_MUTED, 'right');
        }

        foreach ($xTicks as $tick) {
            $x = $this->scaleX((float) $tick, $xMin, $xMax, $left, $right);
            $c->line($x, $top, $x, $bottom, self::GRID_COLOR);
            $c->text(10, $x, $bottom + 20, $this->formatCompact((float) $tick), self::INK_MUTED, 'center');
        }

        // Sumbu
        $c->line($left, $bottom, $right, $bottom, self::AXIS_COLOR);
        $c->line($left, $top, $left, $bottom, self::AXIS_COLOR);

        // Batasi titik agar file tidak membengkak
        $maxPoints = 600;
        $stride = max(1, (int) ceil(count($points) / $maxPoints));

        $hues = [];
        if ($hueCol !== null) {
            foreach ($points as $p) {
                $hues[$p[2]] = true;
            }
            $hues = array_keys($hues);
            sort($hues);
        }

        foreach ($points as $i => $p) {
            if ($i % $stride !== 0) {
                continue;
            }
            $x = $this->scaleX($p[0], $xMin, $xMax, $left, $right);
            $y = $this->scaleY($p[1], $yMin, $yMax, $top, $bottom);

            if ($hueCol !== null) {
                $colorIdx = array_search($p[2], $hues, true);
                $c->dot($x, $y, 4, self::SERIES[((int) $colorIdx) % count(self::SERIES)], 40);
            } else {
                $c->dot($x, $y, 3, self::SINGLE_COLOR, 55);
            }
        }

        // Label sumbu
        $c->text(12, ($left + $right) / 2, $bottom + 48, $c1, self::INK_SECONDARY, 'center', true);
        $c->verticalAxisTitle(12, 28, ($top + $bottom) / 2, $c2, self::INK_SECONDARY);

        // Legenda hue
        if ($hueCol !== null) {
            $legendX = $right + 26;
            $legendY = $top + 10;

            $c->text(11, $legendX, $legendY - 4, $hueCol, self::INK_MUTED);
            foreach ($hues as $i => $label) {
                $y = $legendY + 18 + $i * 24;
                $c->dot($legendX + 6, $y - 4, 5, self::SERIES[$i % count(self::SERIES)]);
                $c->textTruncated(11, $legendX + 20, $y, (string) $label, self::INK_SECONDARY, 120);
            }
        }

        return $this->save($c, $idx, 'scatter_korelasi');
    }

    private function scaleX(float $value, float $min, float $max, int $left, int $right): float
    {
        if ($max <= $min) {
            return ($left + $right) / 2;
        }

        return $left + ($value - $min) / ($max - $min) * ($right - $left);
    }

    private function strengthLabel(float $r): string
    {
        $abs = abs($r);

        return match (true) {
            $abs > 0.7 => 'kuat',
            $abs > 0.4 => 'sedang',
            default => 'lemah',
        };
    }
}
