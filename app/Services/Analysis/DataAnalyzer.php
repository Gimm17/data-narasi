<?php

namespace App\Services\Analysis;

/**
 * DataAnalyzer — port PHP dari python-service/analyzer.py
 *
 * Menghasilkan struktur statistik yang sama dengan versi Python agar
 * prompt_builder, Show.vue, dan Dashboard tetap kompatibel tanpa perubahan.
 */
class DataAnalyzer
{
    /**
     * Suffix penanda kolom ID/kode — dipakai agar kolom nama/label
     * diprioritaskan dibanding kolom ID saat memilih kategori chart.
     */
    private const ID_SUFFIXES = ['_id', '_code', '_no', '_num', '_number', 'id', 'code'];

    /**
     * Jalankan analisis statistik lengkap.
     *
     * @return array<string, mixed>
     */
    public function run(DataTable $table, string $analysisType): array
    {
        $stats = [
            'basic_stats' => $this->basicStats($table),
            'column_info' => $this->columnInfo($table),
            'analysis_type' => $analysisType,
            'insights' => [],
            'enriched' => $this->enrichedStats($table),
        ];

        $domain = match ($analysisType) {
            'penjualan' => $this->analyzeSales($table),
            'keuangan' => $this->analyzeFinance($table),
            'operasional' => $this->analyzeOperational($table),
            'marketing' => $this->analyzeMarketing($table),
            'inventori' => $this->analyzeInventory($table),
            'sdm' => $this->analyzeSdm($table),
            'akademik' => $this->analyzeAkademik($table),
            'kesehatan' => $this->analyzeKesehatan($table),
            'logistik' => $this->analyzeLogistik($table),
            'survey' => $this->analyzeSurvey($table),
            default => $this->analyzeGeneric($table),
        };

        return array_merge($stats, $domain);
    }

    // ───────────────────────── Helper pencarian kolom ─────────────────────────

    /**
     * Cari kolom berdasarkan keyword (case-insensitive).
     *
     * @param array<int, string> $keywords
     * @return array<int, string>
     */
    private function findCols(DataTable $table, array $keywords): array
    {
        $matches = [];
        foreach ($table->columns() as $col) {
            foreach ($keywords as $kw) {
                if (str_contains(strtolower($col), $kw)) {
                    $matches[] = $col;
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * Seperti findCols, tetapi kolom nama/label didahulukan dibanding kolom ID/kode.
     *
     * @param array<int, string> $keywords
     * @return array<int, string>
     */
    private function findLabelCol(DataTable $table, array $keywords): array
    {
        $matches = $this->findCols($table, $keywords);
        if ($matches === []) {
            return [];
        }

        $labels = [];
        $ids = [];
        foreach ($matches as $col) {
            $isId = false;
            foreach (self::ID_SUFFIXES as $suffix) {
                if (str_ends_with(strtolower($col), $suffix)) {
                    $isId = true;
                    break;
                }
            }
            if ($isId) {
                $ids[] = $col;
            } else {
                $labels[] = $col;
            }
        }

        return array_merge($labels, $ids);
    }

    /**
     * Statistik numerik ringkas dengan pembulatan 2 desimal.
     *
     * @return array<string, float|int>
     */
    private function safeNumericStats(DataTable $table, string $column): array
    {
        $stats = $table->numericStats($column);
        if ($stats === []) {
            return [];
        }

        return [
            'total' => round((float) $stats['total'], 2),
            'mean' => round((float) $stats['mean'], 2),
            'median' => round((float) $stats['median'], 2),
            'min' => (float) $stats['min'],
            'max' => (float) $stats['max'],
            'std' => round((float) $stats['std'], 2),
        ];
    }

    // ───────────────────────── Blok statistik dasar ─────────────────────────

    private function basicStats(DataTable $table): array
    {
        return [
            'total_rows' => $table->count(),
            'total_columns' => count($table->columns()),
            'numeric_columns' => count($table->numericColumns()),
            'string_columns' => count($table->stringColumns()),
            'memory_usage_mb' => $table->memoryUsageMb(),
        ];
    }

    private function columnInfo(DataTable $table): array
    {
        $info = [];

        foreach ($table->columns() as $col) {
            $summary = $table->columnSummary($col);
            $entry = [
                'name' => $col,
                'dtype' => $table->type($col),
                'null_count' => $summary['nulls'],
                'null_percentage' => round($summary['nulls'] / max($table->count(), 1) * 100, 1),
                'unique_count' => $summary['unique'],
            ];

            if ($table->type($col) === 'numeric') {
                $entry['is_numeric'] = true;
                $vals = $table->numericColumn($col);
                sort($vals);
                $n = count($vals);

                $entry['min'] = $n > 0 ? min($vals) : null;
                $entry['max'] = $n > 0 ? max($vals) : null;
                $mean = $n > 0 ? array_sum($vals) / $n : null;
                $entry['mean'] = $mean !== null ? round($mean, 2) : null;
                $entry['median'] = $n > 0 ? round(DataTable::median($vals), 2) : null;
                $entry['std'] = $n > 1 ? round(DataTable::stdDev($vals, $mean), 2) : null;

                if ($n > 3) {
                    $skew = DataTable::skewness($vals);
                    $entry['skewness'] = round($skew, 2);

                    $q1 = DataTable::quantile($vals, 0.25);
                    $q3 = DataTable::quantile($vals, 0.75);
                    $iqr = $q3 - $q1;
                    $outliers = 0;
                    if ($iqr > 0) {
                        foreach ($vals as $v) {
                            if ($v < $q1 - 1.5 * $iqr || $v > $q3 + 1.5 * $iqr) {
                                $outliers++;
                            }
                        }
                    }
                    $entry['outlier_count'] = $outliers;
                    $entry['mean_median_ratio'] = round($mean / max((float) DataTable::median($vals), 0.001), 2);

                    $entry['distribution'] = match (true) {
                        abs($skew) < 0.5 => 'normal',
                        $skew > 1.5 => 'highly_right_skewed',
                        $skew > 0.5 => 'right_skewed',
                        $skew < -1.5 => 'highly_left_skewed',
                        default => 'left_skewed',
                    };
                }
            } else {
                $entry['is_numeric'] = false;

                $samples = [];
                foreach ($table->column($col) as $v) {
                    if ($v !== null && $v !== '') {
                        $samples[] = $v;
                        if (count($samples) >= 5) {
                            break;
                        }
                    }
                }
                $entry['sample_values'] = $samples;
                $entry['top_values'] = $table->valueCounts($col, 5);
            }

            $info[$col] = $entry;
        }

        return $info;
    }

    /**
     * Statistik pengayaan untuk prompt builder.
     */
    private function enrichedStats(DataTable $table): array
    {
        $enriched = [];
        $numCols = $table->numericColumns();
        $catCols = [];
        foreach ($table->stringColumns() as $col) {
            if ($table->columnSummary($col)['unique'] <= 20) {
                $catCols[] = $col;
            }
        }

        // Korelasi
        if (count($numCols) >= 2) {
            $pairs = [];
            $n = count($numCols);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $r = $table->correlation($numCols[$i], $numCols[$j]);
                    if ($r !== null) {
                        $pairs[] = ['col1' => $numCols[$i], 'col2' => $numCols[$j], 'r' => round($r, 3)];
                    }
                }
            }
            usort($pairs, fn ($a, $b) => abs($b['r']) <=> abs($a['r']));
            $enriched['correlations'] = array_slice($pairs, 0, 8);
        }

        // Tren temporal
        $dateCols = $this->findCols($table, ['tanggal', 'date', 'time', 'tgl', 'order_date', 'ship_date']);
        if ($dateCols !== [] && $numCols !== []) {
            $trend = $this->temporalTrend($table, $dateCols[0], $numCols[0]);
            if ($trend !== null) {
                $enriched = array_merge($enriched, $trend);
            }
        }

        // Breakdown kategorik
        if ($catCols !== [] && $numCols !== []) {
            $nc = $numCols[0];
            foreach (array_slice($catCols, 0, 2) as $cc) {
                if ($table->columnSummary($cc)['unique'] > 20) {
                    continue;
                }

                $groups = $table->groupBy([$cc], [$nc => 'sum', $nc => 'sum']);
                // groupBy hanya mendukung satu agregasi per kolom; hitung manual
                $groups = $table->groupBy([$cc], [$nc => 'sum']);
                usort($groups, fn ($a, $b) => $b[$nc . '_sum'] <=> $a[$nc . '_sum']);

                $totalSum = 0.0;
                foreach ($table->rows() as $row) {
                    $v = DataTable::tryNumeric($row[$nc] ?? null);
                    if ($v !== null) {
                        $totalSum += (float) $v;
                    }
                }

                $breakdown = [];
                foreach (array_slice($groups, 0, 10) as $g) {
                    $sum = (float) $g[$nc . '_sum'];
                    $breakdown[] = [
                        'label' => (string) $g[$cc],
                        'total' => round($sum, 2),
                        'count' => (int) $g['_count'],
                        'avg' => round($sum / max((int) $g['_count'], 1), 2),
                        'pct' => round($sum / max($totalSum, 1) * 100, 1),
                    ];
                }

                if ($breakdown !== []) {
                    $enriched[$cc . '_breakdown'] = $breakdown;
                }
            }
        }

        // Ringkasan outlier
        $outlierSummary = [];
        foreach (array_slice($numCols, 0, 6) as $nc) {
            $vals = $table->numericColumn($nc);
            if (count($vals) <= 10) {
                continue;
            }
            sort($vals);
            $q1 = DataTable::quantile($vals, 0.25);
            $q3 = DataTable::quantile($vals, 0.75);
            $iqr = $q3 - $q1;
            if ($iqr <= 0) {
                continue;
            }
            $lower = $q1 - 1.5 * $iqr;
            $upper = $q3 + 1.5 * $iqr;
            $extreme = array_values(array_filter($vals, fn ($v) => $v < $lower || $v > $upper));

            if ($extreme !== []) {
                $outlierSummary[] = [
                    'column' => $nc,
                    'count' => count($extreme),
                    'pct' => round(count($extreme) / count($vals) * 100, 1),
                    'max_outlier' => round(max($extreme), 2),
                    'min_outlier' => round(min($extreme), 2),
                ];
            }
        }
        if ($outlierSummary !== []) {
            $enriched['outlier_summary'] = $outlierSummary;
        }

        return $enriched;
    }

    /**
     * Tren tahunan + bulan puncak dari kolom tanggal.
     *
     * @return array<string, mixed>|null
     */
    private function temporalTrend(DataTable $table, string $dateCol, string $valueCol): ?array
    {
        $yearly = [];
        $monthly = [];

        foreach ($table->rows() as $row) {
            $raw = $row[$dateCol] ?? null;
            $val = DataTable::tryNumeric($row[$valueCol] ?? null);
            if ($raw === null || $val === null) {
                continue;
            }

            $ts = is_numeric($raw) ? (int) $raw : strtotime((string) $raw);
            if (!$ts) {
                continue;
            }

            $year = (int) date('Y', $ts);
            $month = date('Y-m', $ts);

            $yearly[$year] ??= ['total' => 0.0, 'count' => 0];
            $yearly[$year]['total'] += (float) $val;
            $yearly[$year]['count']++;

            $monthly[$month] = ($monthly[$month] ?? 0.0) + (float) $val;
        }

        if ($yearly === []) {
            return null;
        }

        ksort($yearly);
        $trend = [];
        foreach ($yearly as $year => $data) {
            $trend[] = [
                'year' => $year,
                'total' => round($data['total'], 2),
                'count' => $data['count'],
                'avg' => round($data['total'] / max($data['count'], 1), 2),
            ];
        }

        $result = ['yearly_trend' => $trend];

        if ($monthly !== []) {
            arsort($monthly);
            $peakMonth = array_key_first($monthly);
            $result['peak_month'] = (string) $peakMonth;
            $result['peak_month_value'] = round((float) $monthly[$peakMonth], 2);
        }

        return $result;
    }

    // ───────────────────────── Analyzer domain ─────────────────────────

    /** PENJUALAN */
    private function analyzeSales(DataTable $table): array
    {
        $insights = [];
        $priceCols = $this->findCols($table, ['harga', 'price', 'total', 'revenue', 'amount', 'nilai']);
        $productCols = $this->findLabelCol($table, ['produk', 'product', 'item', 'nama', 'barang', 'name']);
        $dateCols = $this->findCols($table, ['tanggal', 'date', 'time', 'tgl']);

        if ($priceCols !== []) {
            $insights = array_merge($insights, $this->safeNumericStats($table, $priceCols[0]));
            $insights['revenue_column'] = $priceCols[0];
        }

        // Growth rate: separuh awal vs separuh akhir setelah diurutkan tanggal
        if ($priceCols !== [] && $dateCols !== []) {
            $rows = [];
            foreach ($table->rows() as $row) {
                $raw = $row[$dateCols[0]] ?? null;
                if ($raw === null) {
                    continue;
                }
                $ts = is_numeric($raw) ? (int) $raw : strtotime((string) $raw);
                if ($ts) {
                    $rows[] = ['ts' => $ts, 'val' => DataTable::tryNumeric($row[$priceCols[0]] ?? null)];
                }
            }
            usort($rows, fn ($a, $b) => $a['ts'] <=> $b['ts']);

            $mid = intdiv(count($rows), 2);
            if ($mid > 0) {
                $h1 = 0.0;
                $h2 = 0.0;
                foreach ($rows as $i => $r) {
                    if ($r['val'] === null) {
                        continue;
                    }
                    if ($i < $mid) {
                        $h1 += $r['val'];
                    } else {
                        $h2 += $r['val'];
                    }
                }
                if ($h1 != 0.0) {
                    $insights['growth_rate'] = round(($h2 - $h1) / $h1 * 100, 1);
                }
            }
        }

        if ($productCols !== []) {
            $pc = $productCols[0];
            if ($priceCols !== []) {
                $groups = $table->groupBy([$pc], [$priceCols[0] => 'sum']);
                usort($groups, fn ($a, $b) => $b[$priceCols[0] . '_sum'] <=> $a[$priceCols[0] . '_sum']);
                $top = [];
                foreach (array_slice($groups, 0, 5) as $g) {
                    $top[(string) $g[$pc]] = (float) $g[$priceCols[0] . '_sum'];
                }
                $insights['top_products'] = $top;
            } else {
                $insights['top_products'] = array_map('floatval', $table->valueCounts($pc, 5));
            }
        }

        return ['sales_insights' => $insights];
    }

    /** KEUANGAN */
    private function analyzeFinance(DataTable $table): array
    {
        $insights = [];
        $debitCols = $this->findCols($table, ['debit', 'debet', 'pengeluaran', 'expense', 'biaya', 'cost']);
        $creditCols = $this->findCols($table, ['kredit', 'credit', 'pemasukan', 'income', 'pendapatan', 'revenue']);
        $amountCols = $this->findCols($table, ['jumlah', 'amount', 'saldo', 'balance', 'nominal', 'nilai']);

        if ($debitCols !== []) {
            $insights['total_debit'] = round(array_sum($table->numericColumn($debitCols[0])), 2);
        }
        if ($creditCols !== []) {
            $insights['total_credit'] = round(array_sum($table->numericColumn($creditCols[0])), 2);
        }
        if (isset($insights['total_debit'], $insights['total_credit'])) {
            $insights['net_flow'] = round($insights['total_credit'] - $insights['total_debit'], 2);
        }
        if ($amountCols !== []) {
            $insights['amount_stats'] = $this->safeNumericStats($table, $amountCols[0]);
        }

        $catCols = $this->findCols($table, ['kategori', 'category', 'jenis', 'type', 'akun', 'account']);
        if ($catCols !== [] && $amountCols !== []) {
            $groups = $table->groupBy([$catCols[0]], [$amountCols[0] => 'sum']);
            foreach ($groups as &$g) {
                $g[$amountCols[0] . '_sum'] = abs((float) $g[$amountCols[0] . '_sum']);
            }
            unset($g);
            usort($groups, fn ($a, $b) => $b[$amountCols[0] . '_sum'] <=> $a[$amountCols[0] . '_sum']);

            $top = [];
            foreach (array_slice($groups, 0, 5) as $g) {
                $top[(string) $g[$catCols[0]]] = (float) $g[$amountCols[0] . '_sum'];
            }
            $insights['top_categories'] = $top;
        }

        if ($insights === []) {
            $insights = $this->autoNumericSummary($table);
        }

        return ['finance_insights' => $insights];
    }

    /** OPERASIONAL */
    private function analyzeOperational(DataTable $table): array
    {
        $insights = [];
        $targetCols = $this->findCols($table, ['target', 'goal', 'plan', 'rencana']);
        $actualCols = $this->findCols($table, ['aktual', 'actual', 'realisasi', 'result', 'capaian']);
        $prodCols = $this->findCols($table, ['produksi', 'production', 'output', 'volume']);

        if ($targetCols !== [] && $actualCols !== []) {
            $t = $table->numericColumn($targetCols[0]);
            $a = $table->numericColumn($actualCols[0]);
            if ($t !== [] && $a !== []) {
                $insights['avg_target'] = round(array_sum($t) / count($t), 2);
                $insights['avg_actual'] = round(array_sum($a) / count($a), 2);
                $insights['achievement_rate'] = round(array_sum($a) / max(array_sum($t), 1) * 100, 1);
            }
        }

        if ($prodCols !== []) {
            $insights['production_stats'] = $this->safeNumericStats($table, $prodCols[0]);
        }

        if ($insights === []) {
            $insights = $this->autoNumericSummary($table);
        }

        return ['operational_insights' => $insights];
    }

    /** MARKETING */
    private function analyzeMarketing(DataTable $table): array
    {
        $insights = [];
        $clickCols = $this->findCols($table, ['klik', 'click', 'views', 'impressions', 'impression', 'view']);
        $convCols = $this->findCols($table, ['konversi', 'conversion', 'converted', 'purchase']);
        $budgetCols = $this->findCols($table, ['budget', 'biaya', 'cost', 'spend', 'anggaran']);
        $channelCols = $this->findCols($table, ['channel', 'platform', 'media', 'sumber', 'source', 'campaign']);

        if ($clickCols !== []) {
            $insights['total_clicks'] = (int) array_sum($table->numericColumn($clickCols[0]));
        }
        if ($convCols !== []) {
            $insights['total_conversions'] = (int) array_sum($table->numericColumn($convCols[0]));
        }
        if (isset($insights['total_clicks'], $insights['total_conversions']) && $insights['total_clicks'] > 0) {
            $insights['conversion_rate'] = round($insights['total_conversions'] / $insights['total_clicks'] * 100, 2);
        }
        if ($budgetCols !== []) {
            $budget = array_sum($table->numericColumn($budgetCols[0]));
            $insights['total_budget'] = round($budget, 2);
            if (($insights['total_conversions'] ?? 0) > 0) {
                $insights['cost_per_conversion'] = round($budget / $insights['total_conversions'], 2);
            }
        }
        if ($channelCols !== []) {
            $insights['top_channels'] = $table->valueCounts($channelCols[0], 5);
        }

        if ($insights === []) {
            $insights = $this->autoNumericSummary($table);
        }

        return ['marketing_insights' => $insights];
    }

    /** INVENTORI */
    private function analyzeInventory(DataTable $table): array
    {
        $insights = [];
        $stockCols = $this->findCols($table, ['stok', 'stock', 'qty', 'kuantitas', 'quantity', 'jumlah']);
        $itemCols = $this->findCols($table, ['item', 'produk', 'product', 'barang', 'nama', 'sku']);
        $priceCols = $this->findCols($table, ['harga', 'price', 'cost', 'nilai']);

        if ($stockCols !== []) {
            $s = $table->numericColumn($stockCols[0]);
            sort($s);
            $insights['total_stock'] = (int) array_sum($s);
            $insights['stock_stats'] = $this->safeNumericStats($table, $stockCols[0]);
            if ($s !== []) {
                $lowThreshold = DataTable::quantile($s, 0.1);
                $highThreshold = DataTable::quantile($s, 0.9);
                $insights['low_stock_count'] = count(array_filter($s, fn ($v) => $v <= $lowThreshold));
                $insights['overstock_count'] = count(array_filter($s, fn ($v) => $v >= $highThreshold));
            }
        }

        if ($stockCols !== [] && $itemCols !== []) {
            $rows = $table->rows();
            usort($rows, fn ($a, $b) => (DataTable::tryNumeric($a[$stockCols[0]] ?? null) ?? 0)
                <=> (DataTable::tryNumeric($b[$stockCols[0]] ?? null) ?? 0));

            $lowest = [];
            foreach (array_slice($rows, 0, 5) as $row) {
                $lowest[(string) ($row[$itemCols[0]] ?? '(kosong)')] = (int) (DataTable::tryNumeric($row[$stockCols[0]] ?? null) ?? 0);
            }
            $insights['lowest_stock_items'] = $lowest;
        }

        if ($stockCols !== [] && $priceCols !== []) {
            $total = 0.0;
            foreach ($table->rows() as $row) {
                $qty = DataTable::tryNumeric($row[$stockCols[0]] ?? null);
                $price = DataTable::tryNumeric($row[$priceCols[0]] ?? null);
                if ($qty !== null && $price !== null) {
                    $total += (float) $qty * (float) $price;
                }
            }
            $insights['total_inventory_value'] = round($total, 2);
        }

        if ($insights === []) {
            $insights = $this->autoNumericSummary($table);
        }

        return ['inventory_insights' => $insights];
    }

    /** SDM / HR */
    private function analyzeSdm(DataTable $table): array
    {
        $insights = [];
        $salaryCols = $this->findCols($table, ['gaji', 'salary', 'upah', 'wage', 'kompensasi']);
        $positionCols = $this->findCols($table, ['jabatan', 'position', 'posisi', 'role', 'divisi', 'department', 'departemen']);
        $perfCols = $this->findCols($table, ['kinerja', 'performance', 'skor', 'score', 'rating', 'nilai']);
        $absentCols = $this->findCols($table, ['absen', 'absent', 'hadir', 'attendance', 'izin', 'cuti', 'leave']);

        if ($salaryCols !== []) {
            $insights['salary_stats'] = $this->safeNumericStats($table, $salaryCols[0]);
        }
        if ($salaryCols !== [] && $positionCols !== []) {
            $groups = $table->groupBy([$positionCols[0]], [$salaryCols[0] => 'mean']);
            usort($groups, fn ($a, $b) => $b[$salaryCols[0] . '_mean'] <=> $a[$salaryCols[0] . '_mean']);
            $avg = [];
            foreach (array_slice($groups, 0, 5) as $g) {
                $avg[(string) $g[$positionCols[0]]] = round((float) $g[$salaryCols[0] . '_mean']);
            }
            $insights['avg_salary_by_position'] = $avg;
        }
        if ($perfCols !== []) {
            $insights['performance_stats'] = $this->safeNumericStats($table, $perfCols[0]);
        }
        if ($absentCols !== []) {
            $insights['absence_stats'] = $this->safeNumericStats($table, $absentCols[0]);
        }

        $insights['total_employees'] = $table->count();
        if ($positionCols !== []) {
            $insights['position_distribution'] = $table->valueCounts($positionCols[0], 8);
        }

        if (!isset($insights['salary_stats']) && !isset($insights['performance_stats'])) {
            $insights = array_merge($insights, $this->autoNumericSummary($table));
        }

        return ['sdm_insights' => $insights];
    }

    /** AKADEMIK */
    private function analyzeAkademik(DataTable $table): array
    {
        $insights = [];
        $gradeCols = $this->findCols($table, ['nilai', 'grade', 'score', 'skor', 'ips', 'ipk', 'gpa']);
        $subjectCols = $this->findCols($table, ['mata_pelajaran', 'mapel', 'subject', 'course', 'matkul']);

        if ($gradeCols !== []) {
            $g = $table->numericColumn($gradeCols[0]);
            $insights['grade_stats'] = $this->safeNumericStats($table, $gradeCols[0]);
            if ($g !== []) {
                $threshold = max($g) > 10 ? 70 : 2.5;
                $pass = count(array_filter($g, fn ($v) => $v >= $threshold));
                $insights['pass_rate'] = round($pass / count($g) * 100, 1);
                $insights['fail_count'] = count($g) - $pass;
            }
        }

        if ($gradeCols !== [] && $subjectCols !== []) {
            $groups = $table->groupBy([$subjectCols[0]], [$gradeCols[0] => 'mean']);
            usort($groups, fn ($a, $b) => $b[$gradeCols[0] . '_mean'] <=> $a[$gradeCols[0] . '_mean']);
            $avg = [];
            foreach (array_slice($groups, 0, 10) as $grp) {
                $avg[(string) $grp[$subjectCols[0]]] = round((float) $grp[$gradeCols[0] . '_mean'], 1);
            }
            $insights['avg_grade_by_subject'] = $avg;
        }

        $insights['total_students'] = $table->count();
        if (!isset($insights['grade_stats'])) {
            $insights = array_merge($insights, $this->autoNumericSummary($table));
        }

        return ['akademik_insights' => $insights];
    }

    /** KESEHATAN */
    private function analyzeKesehatan(DataTable $table): array
    {
        $insights = [];
        $diagCols = $this->findCols($table, ['diagnosa', 'diagnosis', 'penyakit', 'disease', 'keluhan']);
        $ageCols = $this->findCols($table, ['usia', 'age', 'umur']);
        $genderCols = $this->findCols($table, ['gender', 'jenis_kelamin', 'kelamin', 'sex']);
        $vitalCols = $this->findCols($table, ['tekanan', 'pressure', 'suhu', 'temperature', 'berat', 'weight', 'tinggi', 'height', 'bmi']);

        if ($diagCols !== []) {
            $insights['top_diagnoses'] = $table->valueCounts($diagCols[0], 8);
        }
        if ($ageCols !== []) {
            $insights['age_stats'] = $this->safeNumericStats($table, $ageCols[0]);
        }
        if ($genderCols !== []) {
            $insights['gender_distribution'] = $table->valueCounts($genderCols[0]);
        }
        foreach (array_slice($vitalCols, 0, 3) as $vc) {
            $insights[$vc . '_stats'] = $this->safeNumericStats($table, $vc);
        }

        $insights['total_patients'] = $table->count();
        if (!isset($insights['top_diagnoses']) && $vitalCols === []) {
            $insights = array_merge($insights, $this->autoNumericSummary($table));
        }

        return ['kesehatan_insights' => $insights];
    }

    /** LOGISTIK */
    private function analyzeLogistik(DataTable $table): array
    {
        $insights = [];
        $destCols = $this->findCols($table, ['tujuan', 'destination', 'kota', 'city', 'alamat', 'address']);
        $statusCols = $this->findCols($table, ['status', 'kondisi', 'state']);
        $timeCols = $this->findCols($table, ['waktu', 'durasi', 'duration', 'lead_time', 'hari', 'days']);
        $weightCols = $this->findCols($table, ['berat', 'weight', 'kg', 'ton', 'volume']);

        if ($statusCols !== []) {
            $insights['status_distribution'] = $table->valueCounts($statusCols[0]);

            $onTimeKeys = ['delivered', 'selesai', 'sukses', 'success', 'on time', 'terkirim'];
            $onTime = 0;
            foreach ($table->column($statusCols[0]) as $v) {
                if ($v !== null && in_array(strtolower((string) $v), $onTimeKeys, true)) {
                    $onTime++;
                }
            }
            if ($table->count() > 0) {
                $insights['on_time_rate'] = round($onTime / $table->count() * 100, 1);
            }
        }
        if ($timeCols !== []) {
            $insights['delivery_time_stats'] = $this->safeNumericStats($table, $timeCols[0]);
        }
        if ($destCols !== []) {
            $insights['top_destinations'] = $table->valueCounts($destCols[0], 5);
        }
        if ($weightCols !== []) {
            $insights['weight_stats'] = $this->safeNumericStats($table, $weightCols[0]);
        }

        $insights['total_shipments'] = $table->count();
        if (!isset($insights['delivery_time_stats']) && !isset($insights['status_distribution'])) {
            $insights = array_merge($insights, $this->autoNumericSummary($table));
        }

        return ['logistik_insights' => $insights];
    }

    /** SURVEY */
    private function analyzeSurvey(DataTable $table): array
    {
        $insights = [];
        $ratingCols = $this->findCols($table, ['rating', 'skor', 'score', 'penilaian', 'nilai', 'satisfaction']);
        $questionCols = $this->findCols($table, ['pertanyaan', 'question', 'aspek', 'kategori', 'dimensi']);

        if ($ratingCols !== []) {
            $r = $table->numericColumn($ratingCols[0]);
            $insights['rating_stats'] = $this->safeNumericStats($table, $ratingCols[0]);
            if ($r !== []) {
                $max = max($r);
                $scaleMax = $max <= 5 ? 5 : ($max <= 10 ? 10 : 100);
                $insights['rating_scale'] = '1-' . $scaleMax;

                $counts = [];
                foreach ($r as $v) {
                    $key = (string) (int) $v;
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
                uksort($counts, fn ($a, $b) => (int) $a <=> (int) $b);
                $insights['rating_distribution'] = $counts;

                if ($scaleMax === 10) {
                    $promoters = count(array_filter($r, fn ($v) => $v >= 9));
                    $detractors = count(array_filter($r, fn ($v) => $v <= 6));
                    $insights['nps_score'] = round(($promoters - $detractors) / count($r) * 100, 1);
                }
            }
        }

        if ($ratingCols !== [] && $questionCols !== []) {
            $groups = $table->groupBy([$questionCols[0]], [$ratingCols[0] => 'mean']);
            $avg = [];
            foreach ($groups as $g) {
                $avg[(string) $g[$questionCols[0]]] = round((float) $g[$ratingCols[0] . '_mean'], 2);
            }
            $insights['avg_rating_by_category'] = $avg;
        }

        $insights['total_respondents'] = $table->count();
        if (!isset($insights['rating_stats'])) {
            $insights = array_merge($insights, $this->autoNumericSummary($table));
        }

        return ['survey_insights' => $insights];
    }

    /** GENERIC */
    private function analyzeGeneric(DataTable $table): array
    {
        return ['generic_insights' => $this->autoNumericSummary($table)];
    }

    /**
     * Ringkasan otomatis kolom numerik + korelasi + top kategori.
     */
    private function autoNumericSummary(DataTable $table): array
    {
        $result = [];
        $numCols = $table->numericColumns();

        foreach (array_slice($numCols, 0, 6) as $col) {
            $result[$col . '_summary'] = $this->safeNumericStats($table, $col);
        }

        if (count($numCols) >= 2) {
            $pairs = [];
            $n = count($numCols);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $r = $table->correlation($numCols[$i], $numCols[$j]);
                    if ($r !== null) {
                        $pairs[] = [$numCols[$i], $numCols[$j], abs($r), $r];
                    }
                }
            }
            usort($pairs, fn ($a, $b) => $b[2] <=> $a[2]);

            $result['top_correlations'] = [];
            foreach (array_slice($pairs, 0, 3) as $p) {
                $result['top_correlations'][] = [
                    'col1' => $p[0],
                    'col2' => $p[1],
                    'correlation' => round($p[3], 3),
                ];
            }
        }

        foreach (array_slice($table->stringColumns(), 0, 3) as $col) {
            $result[$col . '_top_values'] = $table->valueCounts($col, 5);
        }

        return $result;
    }
}
