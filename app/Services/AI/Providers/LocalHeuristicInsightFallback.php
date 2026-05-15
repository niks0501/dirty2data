<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\BusinessInsightProvider;

class LocalHeuristicInsightFallback implements BusinessInsightProvider
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function generate(array $context): array
    {
        $insights = [];
        $dataset = $context['dataset'] ?? [];
        $profile = $context['profile'] ?? [];
        $qualityScore = $context['quality_score'] ?? [];
        $cleaningHistory = $context['cleaning_history'] ?? [];
        $signals = $context['heuristic_signals'] ?? [];

        $rowCount = (int) ($dataset['row_count'] ?? 0);
        $overallScore = (int) ($qualityScore['overall'] ?? 0);
        $opsCount = (int) ($cleaningHistory['operations_count'] ?? 0);

        if ($overallScore >= 85 && $rowCount > 0) {
            return [
                'insights' => [],
                'executive_summary' => 'Your data looks clean. The quality score is '.$overallScore.' out of 100, which means most of it is ready for analysis.',
            ];
        }

        foreach ($profile as $column) {
            $name = (string) ($column['name'] ?? '');
            $missingPct = (float) ($column['missing_percentage'] ?? 0);
            $type = (string) ($column['type'] ?? '');
            $uniqueCount = (int) ($column['unique_count'] ?? 0);

            if ($missingPct >= 15 && $name !== '') {
                $insights[] = [
                    'category' => 'data_action',
                    'title' => 'About '.round($missingPct).'% of the "'.$name.'" column has gaps',
                    'description' => 'This column is missing information for about '.round($missingPct).'% of records. Before making decisions based on this column, consider finding the missing information or noting that your reports will be based on incomplete data.',
                    'severity' => $missingPct >= 30 ? 'warning' : 'info',
                    'related_column' => $name,
                    'business_impact' => 'Reports using this column may show lower totals than reality.',
                ];
            }

            if ($uniqueCount === 1 && $rowCount > 5 && $type !== 'empty' && $name !== '') {
                $val = (string) ($column['most_frequent']['value'] ?? '');
                $insights[] = [
                    'category' => 'risk_flag',
                    'title' => 'The "'.$name.'" column has the same value everywhere'.($val !== '' ? ' ('.$val.')' : ''),
                    'description' => 'Every record in this column shows the same information. This means you cannot use it for comparisons or grouping. If you expected different values here, the data source may need checking.',
                    'severity' => 'info',
                    'related_column' => $name,
                    'business_impact' => 'This column cannot be used for filtering or grouping reports.',
                ];
            }
        }

        if ($opsCount > 0 && count($insights) === 0) {
            $insights[] = [
                'category' => 'data_action',
                'title' => 'Data has been cleaned, now ready for analysis',
                'description' => $opsCount.' cleaning '.($opsCount === 1 ? 'step was' : 'steps were').' applied to this dataset. Review the cleaned data to confirm it matches what you expect, then it should be ready for building reports and charts.',
                'severity' => 'info',
                'related_column' => null,
                'business_impact' => 'You can now build reports and visualizations with more confidence.',
            ];
        }

        if (count($insights) === 0 && $rowCount > 0) {
            $insights[] = [
                'category' => 'data_action',
                'title' => 'Review your data before making decisions',
                'description' => 'The dataset has '.number_format($rowCount).' records across '.(int) ($dataset['column_count'] ?? 0).' columns. Glance through the values in each column to make sure everything looks correct before using it for important decisions.',
                'severity' => 'info',
                'related_column' => null,
                'business_impact' => 'A quick review helps catch surprises before they affect your reports.',
            ];
        }

        return [
            'insights' => array_slice($insights, 0, 5),
            'executive_summary' => $this->summary($rowCount, $overallScore, $opsCount),
        ];
    }

    public function providerName(): string
    {
        return 'local_heuristic';
    }

    public function modelName(): string
    {
        return 'rule-based-fallback';
    }

    private function summary(int $rowCount, int $overallScore, int $opsCount): string
    {
        if ($overallScore >= 85) {
            return 'Your data looks clean with a quality score of '.$overallScore.' out of 100. It should be ready for analysis.';
        }

        $parts = [];

        if ($rowCount > 0) {
            $parts[] = 'This dataset has '.number_format($rowCount).' records.';
        }

        if ($opsCount > 0) {
            $parts[] = $opsCount.' cleaning '.($opsCount === 1 ? 'step has' : 'steps have').' already been applied.';
        }

        return implode(' ', $parts).' Review the points above before using this data for important decisions.';
    }
}
