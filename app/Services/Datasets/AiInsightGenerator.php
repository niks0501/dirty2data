<?php

declare(strict_types=1);

namespace App\Services\Datasets;

use App\Models\Dataset;
use App\Models\DatasetAiInsight;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiInsightGenerator
{
    private const int MAX_AI_INSIGHTS = 5;

    /**
     * Generate AI-powered insights for a dataset using Gemini.
     *
     * @return list<DatasetAiInsight>
     */
    public function generate(Dataset $dataset, array $ruleBasedInsights): array
    {
        $apiKey = (string) config('ai.providers.gemini.api_key');

        if ($apiKey === '' || $apiKey === 'your_api_key_here') {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $prompt = $this->buildPrompt($dataset, $ruleBasedInsights);
        $responseText = $this->callGemini($prompt);
        $parsed = $this->parseResponse($responseText);

        return $this->persistInsights($dataset, $parsed);
    }

    private function buildPrompt(Dataset $dataset, array $ruleBasedInsights): string
    {
        $systemPrompt = $this->loadPrompt();

        $context = [
            'dataset_name' => $dataset->original_name,
            'row_count' => $dataset->row_count,
            'column_count' => $dataset->column_count,
            'headers' => $dataset->headers ?? [],
            'profile' => $this->summarizeProfile($dataset),
            'rule_based_insights' => $ruleBasedInsights,
        ];

        return $systemPrompt."\n\nDataset context:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function summarizeProfile(Dataset $dataset): array
    {
        $profile = $dataset->profile ?? [];
        $columns = $profile['columns'] ?? [];

        return array_map(function (array $col): array {
            return [
                'name' => $col['name'] ?? '',
                'type' => $col['type'] ?? 'unknown',
                'missing_percentage' => $col['missing_percentage'] ?? 0,
                'unique_count' => $col['unique_count'] ?? 0,
                'average' => $col['average'] ?? null,
                'median' => $col['median'] ?? null,
                'minimum' => $col['minimum'] ?? null,
                'maximum' => $col['maximum'] ?? null,
                'sample_values' => array_slice($col['sample_values'] ?? [], 0, 5),
                'most_frequent' => $col['most_frequent'] ?? null,
            ];
        }, $columns);
    }

    private function callGemini(string $prompt): string
    {
        $apiKey = (string) config('ai.providers.gemini.api_key');
        $baseUrl = rtrim((string) config('ai.providers.gemini.base_url'), '/');
        $model = (string) config('ai.model', 'gemini-2.5-flash-lite');

        $response = Http::timeout((int) config('ai.timeout', 20))
            ->acceptJson()
            ->post("{$baseUrl}/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.3,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('AI insight generation request failed.');
        }

        $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');

        if ($text === '') {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        return $this->extractJson($text);
    }

    private function extractJson(string $text): string
    {
        $trimmed = trim($text);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }

    /**
     * @return list<array{category: string, title: string, description: string, severity: string, related_column: string|null}>
     */
    private function parseResponse(string $responseText): array
    {
        $decoded = json_decode($responseText, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('AI returned invalid JSON.');
        }

        $rawInsights = $decoded['insights'] ?? $decoded;

        if (! is_array($rawInsights)) {
            throw new RuntimeException('AI response missing insights array.');
        }

        $parsed = [];

        foreach ($rawInsights as $item) {
            if (! is_array($item)) {
                continue;
            }

            $parsed[] = [
                'category' => (string) ($item['category'] ?? 'general'),
                'title' => (string) ($item['title'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'severity' => (string) ($item['severity'] ?? 'info'),
                'related_column' => isset($item['related_column']) && is_string($item['related_column'])
                    ? $item['related_column']
                    : null,
            ];

            if (count($parsed) >= self::MAX_AI_INSIGHTS) {
                break;
            }
        }

        return $parsed;
    }

    /**
     * @param  list<array{category: string, title: string, description: string, severity: string, related_column: string|null}>  $insights
     * @return list<DatasetAiInsight>
     */
    private function persistInsights(Dataset $dataset, array $insights): array
    {
        $dataset->aiInsights()->delete();

        $models = [];

        foreach ($insights as $insight) {
            $models[] = DatasetAiInsight::create([
                'dataset_id' => $dataset->id,
                'category' => $insight['category'],
                'title' => $insight['title'],
                'description' => $insight['description'],
                'severity' => $insight['severity'],
                'related_column' => $insight['related_column'],
            ]);
        }

        return $models;
    }

    private function loadPrompt(): string
    {
        $path = resource_path('prompts/ai-insight-generation.txt');

        if (! is_file($path)) {
            throw new RuntimeException('AI insight generation prompt file is missing.');
        }

        return (string) file_get_contents($path);
    }
}
