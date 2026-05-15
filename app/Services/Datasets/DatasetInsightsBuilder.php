<?php

declare(strict_types=1);

namespace App\Services\Datasets;

use App\Models\Dataset;

class DatasetInsightsBuilder
{
    /**
     * Threshold ratio for detecting skew: if |mean - median| / max(|mean|,|median|,1) > this,
     * the distribution is considered skewed.
     */
    private const float SKEW_SENSITIVITY = 0.1;

    /**
     * If the most-frequent value appears in more than this fraction of non-blank rows,
     * the column is flagged as dominated by a single segment.
     */
    private const float DOMINANCE_THRESHOLD = 0.5;

    /**
     * Missing percentage above which a column receives a warning severity.
     */
    private const float HIGH_MISSING_THRESHOLD = 20.0;

    /** Pearson |r| threshold for "strong" correlation. */
    private const float STRONG_CORRELATION = 0.7;

    /** Pearson |r| threshold for "moderate" correlation. */
    private const float MODERATE_CORRELATION = 0.4;

    /** Maximum number of insights returned in the final array. */
    private const int MAX_INSIGHTS = 12;

    /**
     * For row-by-row trend detection: if >= 70 % of consecutive value pairs move
     * in the same direction, the column is flagged as monotonic.
     */
    private const float TREND_CONSISTENCY = 0.7;

    /**
     * Maximum number of numeric column pairs to check for correlation (avoids
     * O(n²) blow-up on wide datasets).
     */
    private const int MAX_CORRELATION_PAIRS = 15;

    // ---------------------------------------------------------------
    //  Construction (follows DatasetChartBuilder / DatasetProfiler)
    // ---------------------------------------------------------------

    /**
     * @param  DatasetTypeDetector|null  $typeDetector  Optional detector for blank-value checks.
     *                                                  When null, a fresh instance is created lazily.
     */
    public function __construct(private readonly ?DatasetTypeDetector $typeDetector = null) {}

    // ---------------------------------------------------------------
    //  Public API
    // ---------------------------------------------------------------

    /**
     * Generate plain-language insights from the dataset profile data.
     *
     * Analyzes the column-level profile stored on the Dataset model and produces
     * a curated set of human-readable insights across six categories: quality,
     * distribution, outlier, segment, trend, and correlation.
     *
     * @param  Dataset  $dataset  The dataset model with a populated `profile` array.
     * @return array{insights: list<array<string, mixed>>, generated_at: string, summary: string}
     */
    public function build(Dataset $dataset): array
    {
        /** @var array<string, mixed> $profile */
        $profile = $dataset->profile ?? [];

        /** @var list<array<string, mixed>> $columns */
        $columns = $profile['columns'] ?? [];

        $rowCount = (int) ($profile['row_count'] ?? 0);
        $duplicateCount = (int) ($profile['duplicate_count'] ?? 0);

        $insights = array_merge(
            $this->qualityInsights($columns, $rowCount, $duplicateCount),
            $this->distributionInsights($columns),
            $this->outlierInsights($columns),
            $this->segmentInsights($columns, $rowCount),
            $this->trendInsights($dataset, $columns),
            $this->correlationInsights($dataset, $columns),
        );

        $insights = $this->prioritizeAndCap($insights);
        $summary = $this->generateSummary($insights, $profile);

        return [
            'insights' => $insights,
            'generated_at' => now()->toIso8601String(),
            'summary' => $summary,
        ];
    }

    // ---------------------------------------------------------------
    //  Insight generators
    // ---------------------------------------------------------------

    /**
     * Generate data-quality insights from column-level missing-value stats.
     *
     * @param  list<array<string, mixed>>  $columns
     * @return list<array<string, mixed>>
     */
    private function qualityInsights(array $columns, int $rowCount, int $duplicateCount): array
    {
        $insights = [];
        $seq = 0;

        // --- Overall completeness ---
        $columnCount = count($columns);
        $completelyMissing = [];
        $highMissing = [];

        foreach ($columns as $col) {
            $missingPct = (float) ($col['missing_percentage'] ?? 0);
            $colName = (string) ($col['name'] ?? '');

            if ($missingPct === 100.0) {
                $completelyMissing[] = $colName;
            } elseif ($missingPct >= self::HIGH_MISSING_THRESHOLD) {
                $highMissing[] = ['name' => $colName, 'pct' => $missingPct];
            }
        }

        // General overview
        if ($rowCount > 0) {
            $seq++;
            $insights[] = $this->makeInsight(
                id: 'quality_'.$seq,
                category: 'quality',
                title: 'Dataset overview',
                description: "This dataset contains {$rowCount} rows across {$columnCount} columns, providing a ".($rowCount > 1000 ? 'substantial' : 'modest').' foundation for analysis.',
                severity: 'info',
                metadata: ['row_count' => $rowCount, 'column_count' => $columnCount],
            );
        }

        // Duplicates
        if ($duplicateCount > 0) {
            $seq++;
            $dupPct = $rowCount > 0 ? round(($duplicateCount / $rowCount) * 100, 1) : 0;
            $severity = $dupPct > 10 ? 'warning' : 'info';
            $insights[] = $this->makeInsight(
                id: 'quality_'.$seq,
                category: 'quality',
                title: 'Duplicate rows detected',
                description: "{$duplicateCount} rows ({$dupPct}%) are exact duplicates of other rows. Consider removing them to avoid skewed analysis.",
                severity: $severity,
                metadata: ['duplicate_count' => $duplicateCount, 'duplicate_percentage' => $dupPct],
            );
        } elseif ($rowCount > 0) {
            $seq++;
            $insights[] = $this->makeInsight(
                id: 'quality_'.$seq,
                category: 'quality',
                title: 'No duplicate rows',
                description: 'All rows in this dataset are unique — no duplicate records were found.',
                severity: 'positive',
            );
        }

        // Completely empty columns
        foreach ($completelyMissing as $colName) {
            $seq++;
            $insights[] = $this->makeInsight(
                id: 'quality_'.$seq,
                category: 'quality',
                title: "Column \"{$colName}\" is empty",
                description: "Every value in column \"{$colName}\" is blank. You may want to remove or ignore this column during analysis.",
                severity: 'warning',
                relatedColumn: $colName,
                metadata: ['missing_percentage' => 100.0],
            );
        }

        // High-missing columns
        foreach ($highMissing as $item) {
            $seq++;
            $insights[] = $this->makeInsight(
                id: 'quality_'.$seq,
                category: 'quality',
                title: "High missing rate in \"{$item['name']}\"",
                description: "{$item['pct']}% of values in \"{$item['name']}\" are missing — this may affect the reliability of statistics for this column.",
                severity: 'warning',
                relatedColumn: $item['name'],
                metadata: ['missing_percentage' => $item['pct']],
            );
        }

        // Clean bill-of-health when nothing is flagged
        if ($rowCount > 0 && $completelyMissing === [] && $highMissing === [] && $duplicateCount === 0) {
            // We already added the positive "no duplicates" and overview; skip adding redundant positives
        }

        return $insights;
    }

    /**
     * Generate distribution-characterization insights for numeric columns.
     *
     * Compares mean vs. median to detect skew and describes the shape
     * in plain language.
     *
     * @param  list<array<string, mixed>>  $columns
     * @return list<array<string, mixed>>
     */
    private function distributionInsights(array $columns): array
    {
        $insights = [];
        $seq = 0;

        foreach ($columns as $col) {
            if (($col['type'] ?? '') !== 'numeric') {
                continue;
            }

            $colName = (string) ($col['name'] ?? '');
            $mean = $col['average'] ?? null;
            $median = $col['median'] ?? null;
            $min = $col['minimum'] ?? null;
            $max = $col['maximum'] ?? null;
            $uniqueCount = (int) ($col['unique_count'] ?? 0);

            if ($mean === null || $median === null || $min === null || $max === null) {
                continue;
            }

            $range = (float) $max - (float) $min;
            $denominator = max(abs((float) $mean), abs((float) $median), 1.0);
            $skewRatio = ($denominator > 0) ? ((float) $mean - (float) $median) / $denominator : 0.0;

            if ($range === 0.0) {
                $seq++;
                $insights[] = $this->makeInsight(
                    id: 'dist_'.$seq,
                    category: 'distribution',
                    title: "Constant values in \"{$colName}\"",
                    description: "All values in \"{$colName}\" are the same ({$min}). This column will not contribute to variance-based analysis.",
                    severity: 'neutral',
                    relatedColumn: $colName,
                    metadata: ['mean' => $mean, 'median' => $median, 'min' => $min, 'max' => $max],
                );

                continue;
            }

            if ($skewRatio > self::SKEW_SENSITIVITY) {
                $seq++;
                $insights[] = $this->makeInsight(
                    id: 'dist_'.$seq,
                    category: 'distribution',
                    title: "Right-skewed distribution in \"{$colName}\"",
                    description: "The average ({$mean}) is noticeably higher than the median ({$median}), indicating a right-skewed distribution. A few high values are pulling the mean upward.",
                    severity: 'info',
                    relatedColumn: $colName,
                    metadata: ['mean' => $mean, 'median' => $median, 'skew_direction' => 'right'],
                );
            } elseif ($skewRatio < -self::SKEW_SENSITIVITY) {
                $seq++;
                $insights[] = $this->makeInsight(
                    id: 'dist_'.$seq,
                    category: 'distribution',
                    title: "Left-skewed distribution in \"{$colName}\"",
                    description: "The average ({$mean}) is noticeably lower than the median ({$median}), indicating a left-skewed distribution. A few low values are pulling the mean downward.",
                    severity: 'info',
                    relatedColumn: $colName,
                    metadata: ['mean' => $mean, 'median' => $median, 'skew_direction' => 'left'],
                );
            } elseif ($uniqueCount <= 3 && $range > 0) {
                $seq++;
                $insights[] = $this->makeInsight(
                    id: 'dist_'.$seq,
                    category: 'distribution',
                    title: "Low-cardinality numeric in \"{$colName}\"",
                    description: "\"{$colName}\" only contains {$uniqueCount} distinct numeric values (min: {$min}, max: {$max}). It may behave more like a categorical label than a continuous measurement.",
                    severity: 'info',
                    relatedColumn: $colName,
                    metadata: ['unique_count' => $uniqueCount, 'min' => $min, 'max' => $max],
                );
            }
        }

        return $insights;
    }

    /**
     * Generate outlier-summary insights from the IQR-based outlier detection
     * already present in each numeric column's profile.
     *
     * @param  list<array<string, mixed>>  $columns
     * @return list<array<string, mixed>>
     */
    private function outlierInsights(array $columns): array
    {
        $insights = [];
        $seq = 0;

        foreach ($columns as $col) {
            $outliers = $col['outliers_iqr'] ?? null;

            if (! is_array($outliers) || ((int) ($outliers['count'] ?? 0)) === 0) {
                continue;
            }

            $colName = (string) ($col['name'] ?? '');
            $count = (int) $outliers['count'];
            $lower = (float) ($outliers['lower_bound'] ?? 0);
            $upper = (float) ($outliers['upper_bound'] ?? 0);
            $totalNonBlank = (int) ($col['unique_count'] ?? 0); // approximation — unique ≠ total-nonblank
            $pct = $totalNonBlank > 0 ? round(($count / $totalNonBlank) * 100, 1) : 0;

            $severity = $pct > 15 ? 'warning' : 'info';

            $seq++;
            $insights[] = $this->makeInsight(
                id: 'outlier_'.$seq,
                category: 'outlier',
                title: "Outliers detected in \"{$colName}\"",
                description: "\"{$colName}\" has {$count} outlier".($count !== 1 ? 's' : '')." (values below {$lower} or above {$upper}). ".(($count > 5) ? 'These extreme values may distort averages — consider reviewing or capping them.' : 'A small number of extreme values were found — they may be legitimate data points.'),
                severity: $severity,
                relatedColumn: $colName,
                metadata: [
                    'outlier_count' => $count,
                    'outlier_percentage' => $pct,
                    'lower_bound' => $lower,
                    'upper_bound' => $upper,
                    'q1' => $outliers['q1'] ?? null,
                    'q3' => $outliers['q3'] ?? null,
                ],
            );
        }

        return $insights;
    }

    /**
     * Generate top-segment insights by identifying columns where a single
     * value dominates the distribution.
     *
     * @param  list<array<string, mixed>>  $columns
     * @return list<array<string, mixed>>
     */
    private function segmentInsights(array $columns, int $rowCount): array
    {
        $insights = [];
        $seq = 0;

        foreach ($columns as $col) {
            $mostFreq = $col['most_frequent'] ?? null;

            if (! is_array($mostFreq)) {
                continue;
            }

            $freqCount = (int) ($mostFreq['count'] ?? 0);
            $freqValue = (string) ($mostFreq['value'] ?? '');

            if ($freqCount === 0 || $freqValue === '' || $rowCount === 0) {
                continue;
            }

            $nonBlankEstimate = $rowCount - (int) ($col['missing_count'] ?? 0);

            if ($nonBlankEstimate <= 0) {
                continue;
            }

            $dominance = $freqCount / $nonBlankEstimate;

            if ($dominance < self::DOMINANCE_THRESHOLD) {
                continue;
            }

            $colName = (string) ($col['name'] ?? '');
            $pct = round($dominance * 100, 1);
            $severity = $dominance > 0.8 ? 'warning' : 'info';

            $seq++;
            $insights[] = $this->makeInsight(
                id: 'segment_'.$seq,
                category: 'segment',
                title: "Dominant value in \"{$colName}\"",
                description: "\"{$freqValue}\" appears in {$pct}% of non-blank rows of \"{$colName}\". ".(($dominance > 0.8) ? 'This column may have too little variation to be useful for grouping or analysis.' : 'This segment dominates but other categories still have meaningful presence.'),
                severity: $severity,
                relatedColumn: $colName,
                metadata: [
                    'dominant_value' => $freqValue,
                    'dominant_count' => $freqCount,
                    'dominant_percentage' => $pct,
                ],
            );
        }

        return $insights;
    }

    /**
     * Generate trend-detection insights by examining row-by-row monotonicity
     * of numeric columns in the cleaned records.
     *
     * When a date column is present, the rows are sorted by that date before
     * checking for trends. Otherwise the natural row order is used.
     *
     * @param  list<array<string, mixed>>  $columns
     * @return list<array<string, mixed>>
     */
    private function trendInsights(Dataset $dataset, array $columns): array
    {
        /** @var list<array<string, mixed>> $records */
        $records = $dataset->cleaned_records ?? [];

        if ($records === []) {
            return [];
        }

        // Identify date columns (to sort by) and numeric columns (to check)
        $dateColumns = [];
        $numericColumns = [];

        foreach ($columns as $col) {
            $type = $col['type'] ?? '';
            $name = (string) ($col['name'] ?? '');

            if ($type === 'date') {
                $dateColumns[] = $name;
            } elseif ($type === 'numeric') {
                $numericColumns[] = $name;
            }
        }

        if ($numericColumns === []) {
            return [];
        }

        // If we have a date column, sort records by it and check for numeric trends
        $sortColumn = $dateColumns !== [] ? $dateColumns[0] : null;

        $sortedRecords = $records;

        if ($sortColumn !== null) {
            usort($sortedRecords, function (array $a, array $b) use ($sortColumn): int {
                $ta = strtotime((string) ($a[$sortColumn] ?? ''));
                $tb = strtotime((string) ($b[$sortColumn] ?? ''));

                if ($ta === false && $tb === false) {
                    return 0;
                }

                if ($ta === false) {
                    return 1;
                }

                if ($tb === false) {
                    return -1;
                }

                return $ta <=> $tb;
            });
        }

        $insights = [];
        $seq = 0;

        foreach ($numericColumns as $colName) {
            // Extract numeric values in sort order
            $values = [];

            foreach ($sortedRecords as $record) {
                $val = $record[$colName] ?? null;

                if ($this->isBlank($val) || ! is_numeric($val)) {
                    continue;
                }

                $values[] = (float) $val;
            }

            if (count($values) < 3) {
                continue;
            }

            $direction = $this->trendDirection($values);

            if ($direction === 'none') {
                continue;
            }

            $seq++;
            $label = $sortColumn !== null ? " over \"{$sortColumn}\"" : ' across rows';

            if ($direction === 'up') {
                $insights[] = $this->makeInsight(
                    id: 'trend_'.$seq,
                    category: 'trend',
                    title: "Upward trend in \"{$colName}\"",
                    description: "\"{$colName}\" shows a consistent upward trend{$label}. Values tend to increase from row to row, which may indicate growth or accumulation.",
                    severity: 'info',
                    relatedColumn: $colName,
                    metadata: ['trend_direction' => 'up', 'sorted_by' => $sortColumn],
                );
            } else {
                $insights[] = $this->makeInsight(
                    id: 'trend_'.$seq,
                    category: 'trend',
                    title: "Downward trend in \"{$colName}\"",
                    description: "\"{$colName}\" shows a consistent downward trend{$label}. Values tend to decrease from row to row, which may indicate decline or depletion.",
                    severity: 'info',
                    relatedColumn: $colName,
                    metadata: ['trend_direction' => 'down', 'sorted_by' => $sortColumn],
                );
            }
        }

        return $insights;
    }

    /**
     * Generate correlation-highlight insights by computing pairwise Pearson
     * correlation between numeric columns in the cleaned records.
     *
     * @param  list<array<string, mixed>>  $columns
     * @return list<array<string, mixed>>
     */
    private function correlationInsights(Dataset $dataset, array $columns): array
    {
        /** @var list<array<string, mixed>> $records */
        $records = $dataset->cleaned_records ?? [];

        // Find numeric columns
        $numericCols = [];

        foreach ($columns as $col) {
            if (($col['type'] ?? '') === 'numeric') {
                $numericCols[] = (string) ($col['name'] ?? '');
            }
        }

        if (count($numericCols) < 2 || $records === []) {
            return [];
        }

        // Extract numeric vectors
        $vectors = [];

        foreach ($numericCols as $colName) {
            $values = [];

            foreach ($records as $record) {
                $val = $record[$colName] ?? null;

                if ($this->isBlank($val) || ! is_numeric($val)) {
                    continue;
                }

                $values[] = (float) $val;
            }

            if (count($values) >= 3) {
                $vectors[$colName] = $values;
            }
        }

        if (count($vectors) < 2) {
            return [];
        }

        $colNames = array_keys($vectors);
        $pairCount = 0;
        $results = [];

        for ($i = 0; $i < count($colNames); $i++) {
            for ($j = $i + 1; $j < count($colNames); $j++) {
                if ($pairCount >= self::MAX_CORRELATION_PAIRS) {
                    break 2;
                }

                $a = $colNames[$i];
                $b = $colNames[$j];

                // Align vectors on common indices
                $minLen = min(count($vectors[$a]), count($vectors[$b]));
                $xA = array_slice($vectors[$a], 0, $minLen);
                $xB = array_slice($vectors[$b], 0, $minLen);

                $r = $this->pearsonCorrelation($xA, $xB);

                if ($r === null) {
                    continue;
                }

                $absR = abs($r);

                if ($absR < self::MODERATE_CORRELATION) {
                    continue;
                }

                $results[] = ['col_a' => $a, 'col_b' => $b, 'r' => $r, 'abs' => $absR];
                $pairCount++;
            }
        }

        if ($results === []) {
            return [];
        }

        // Sort by absolute correlation strength descending, take top few
        usort($results, fn (array $a, array $b): int => $b['abs'] <=> $a['abs']);
        $results = array_slice($results, 0, 3);

        $insights = [];
        $seq = 0;

        foreach ($results as $result) {
            $seq++;
            $r = (float) $result['r'];
            $absR = abs($r);
            $direction = $r > 0 ? 'positive' : 'negative';

            if ($absR >= self::STRONG_CORRELATION) {
                $strength = 'strong';
            } else {
                $strength = 'moderate';
            }

            $insights[] = $this->makeInsight(
                id: 'corr_'.$seq,
                category: 'correlation',
                title: ucfirst($strength)." {$direction} correlation: \"{$result['col_a']}\" ↔ \"{$result['col_b']}\"",
                description: "\"{$result['col_a']}\" and \"{$result['col_b']}\" show a {$strength} {$direction} relationship (r = {$r}). ".(($r > 0) ? 'As one increases, the other tends to increase as well.' : 'As one increases, the other tends to decrease.'),
                severity: $absR >= self::STRONG_CORRELATION ? 'positive' : 'info',
                metadata: [
                    'column_a' => $result['col_a'],
                    'column_b' => $result['col_b'],
                    'pearson_r' => $r,
                    'strength' => $strength,
                    'direction' => $direction,
                ],
            );
        }

        return $insights;
    }

    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    /**
     * Cap the total number of insights and re-index IDs sequentially.
     *
     * Prioritization rules:
     *  1. "warning" before "info/positive/neutral"
     *  2. Insights without a related column (global) before column-specific ones
     *  3. Preserve original order within each priority group
     *
     * @param  list<array<string, mixed>>  $insights
     * @return list<array<string, mixed>>
     */
    private function prioritizeAndCap(array $insights): array
    {
        if (count($insights) <= self::MAX_INSIGHTS) {
            return $insights;
        }

        $severityRank = [
            'warning' => 0,
            'info' => 1,
            'positive' => 2,
            'neutral' => 3,
        ];

        usort($insights, function (array $a, array $b) use ($severityRank): int {
            $rankA = $severityRank[(string) ($a['severity'] ?? 'info')] ?? 1;
            $rankB = $severityRank[(string) ($b['severity'] ?? 'info')] ?? 1;

            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            // Global insights (null related_column) before column-specific ones
            $colA = $a['related_column'] ?? null;
            $colB = $b['related_column'] ?? null;

            if ($colA === null && $colB !== null) {
                return -1;
            }

            if ($colA !== null && $colB === null) {
                return 1;
            }

            return 0;
        });

        return array_slice($insights, 0, self::MAX_INSIGHTS);
    }

    /**
     * Generate a one-sentence plain-language summary of the most notable insights.
     *
     * @param  list<array<string, mixed>>  $insights
     * @param  array<string, mixed>  $profile
     */
    private function generateSummary(array $insights, array $profile): string
    {
        $rowCount = (int) ($profile['row_count'] ?? 0);
        $columnCount = (int) ($profile['column_count'] ?? 0);

        if ($insights === []) {
            return "This dataset has {$rowCount} rows and {$columnCount} columns. No notable patterns or quality concerns were automatically detected.";
        }

        $warnings = array_filter($insights, fn (array $i): bool => ($i['severity'] ?? '') === 'warning');
        $correlations = array_filter($insights, fn (array $i): bool => ($i['category'] ?? '') === 'correlation');
        $trends = array_filter($insights, fn (array $i): bool => ($i['category'] ?? '') === 'trend');

        $parts = [];
        $parts[] = "{$rowCount} rows, {$columnCount} columns";

        if (count($warnings) > 0) {
            $parts[] = count($warnings).' data quality '.(count($warnings) === 1 ? 'concern' : 'concerns');
        }

        if (count($correlations) > 0) {
            $parts[] = count($correlations).' notable correlation'.(count($correlations) > 1 ? 's' : '');
        }

        if (count($trends) > 0) {
            $parts[] = count($trends).' trend detection'.(count($trends) > 1 ? 's' : '');
        }

        return 'Dataset summary: '.implode(', ', $parts).'.';
    }

    /**
     * Build a single insight array with the canonical shape.
     *
     * @return array{id: string, category: string, title: string, description: string, severity: string, related_column: string|null, metadata: array<string, mixed>|null}
     */
    private function makeInsight(
        string $id,
        string $category,
        string $title,
        string $description,
        string $severity = 'info',
        ?string $relatedColumn = null,
        ?array $metadata = null,
    ): array {
        return [
            'id' => $id,
            'category' => $category,
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
            'related_column' => $relatedColumn,
            'metadata' => $metadata,
        ];
    }

    /**
     * Determine the dominant direction of a numeric sequence.
     *
     * Returns 'up' if >= TREND_CONSISTENCY of consecutive pairs increase,
     * 'down' if >= TREND_CONSISTENCY decrease, and 'none' otherwise.
     *
     * @param  list<float>  $values
     */
    private function trendDirection(array $values): string
    {
        $ups = 0;
        $downs = 0;
        $total = count($values) - 1;

        if ($total <= 0) {
            return 'none';
        }

        for ($i = 0; $i < $total; $i++) {
            if ($values[$i + 1] > $values[$i]) {
                $ups++;
            } elseif ($values[$i + 1] < $values[$i]) {
                $downs++;
            }
        }

        if ($ups === 0 && $downs === 0) {
            return 'none';
        }

        $upsRatio = $ups / ($ups + $downs);
        $downsRatio = $downs / ($ups + $downs);

        if ($upsRatio >= self::TREND_CONSISTENCY) {
            return 'up';
        }

        if ($downsRatio >= self::TREND_CONSISTENCY) {
            return 'down';
        }

        return 'none';
    }

    /**
     * Compute the Pearson correlation coefficient between two equal-length
     * numeric arrays.
     *
     * @param  list<float>  $x
     * @param  list<float>  $y
     */
    private function pearsonCorrelation(array $x, array $y): ?float
    {
        $n = count($x);

        if ($n < 3 || count($y) !== $n) {
            return null;
        }

        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;

        $covariance = 0.0;
        $varianceX = 0.0;
        $varianceY = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanX;
            $dy = $y[$i] - $meanY;
            $covariance += $dx * $dy;
            $varianceX += $dx * $dx;
            $varianceY += $dy * $dy;
        }

        if ($varianceX === 0.0 || $varianceY === 0.0) {
            return null;
        }

        return round($covariance / sqrt($varianceX * $varianceY), 4);
    }

    /**
     * Check whether a value is considered blank (null, empty string, or
     * whitespace-only), delegated to the configured type detector.
     */
    private function isBlank(mixed $value): bool
    {
        return $this->detector()->isBlank($value);
    }

    /**
     * Lazily resolve the type-detector dependency, mirroring the pattern
     * used by DatasetChartBuilder and DatasetProfiler.
     */
    private function detector(): DatasetTypeDetector
    {
        return $this->typeDetector ?? new DatasetTypeDetector;
    }
}
