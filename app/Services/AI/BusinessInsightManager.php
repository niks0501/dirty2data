<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\BusinessInsightProvider;
use App\Services\AI\Providers\GeminiBusinessInsightProvider;
use App\Services\AI\Providers\LocalHeuristicInsightFallback;
use Illuminate\Support\Facades\Log;

class BusinessInsightManager
{
    public function __construct(
        private readonly GeminiBusinessInsightProvider $gemini,
        private readonly LocalHeuristicInsightFallback $heuristic,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array{provider: string, model: string, source: string, fallback_reason: string|null, raw_response: array<string, mixed>, insights: list<array<string, mixed>>, executive_summary: string}
     */
    public function generate(array $context): array
    {
        $provider = $this->provider();
        $fallbackReason = null;

        try {
            if ((bool) config('ai.enabled', true) && $provider->providerName() === 'gemini') {
                $raw = $provider->generate($context);
                $parsed = $this->parseResponse($raw);

                if ($parsed['insights'] !== []) {
                    return [
                        'provider' => $provider->providerName(),
                        'model' => $provider->modelName(),
                        'source' => 'ai',
                        'fallback_reason' => null,
                        'raw_response' => $raw,
                        'insights' => $parsed['insights'],
                        'executive_summary' => $parsed['executive_summary'],
                    ];
                }

                $fallbackReason = 'Gemini returned no insights for this dataset.';
            }
        } catch (\Throwable $e) {
            $fallbackReason = 'AI insight service is temporarily unavailable. Using basic analysis instead.';
            Log::warning('AI business insights fell back to local heuristics.', [
                'reason' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
        }

        $raw = $this->heuristic->generate($context);
        $parsed = $this->parseResponse($raw);

        return [
            'provider' => $this->heuristic->providerName(),
            'model' => $this->heuristic->modelName(),
            'source' => 'heuristic',
            'fallback_reason' => $fallbackReason,
            'raw_response' => $raw,
            'insights' => $parsed['insights'],
            'executive_summary' => $parsed['executive_summary'],
        ];
    }

    private function provider(): BusinessInsightProvider
    {
        return match ((string) config('ai.provider', 'gemini')) {
            'gemini' => $this->gemini,
            default => $this->heuristic,
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{insights: list<array<string, mixed>>, executive_summary: string}
     */
    private function parseResponse(array $raw): array
    {
        $rawInsights = $raw['insights'] ?? [];

        if (! is_array($rawInsights)) {
            $rawInsights = [];
        }

        $parsed = [];
        $maxInsights = 5;

        foreach ($rawInsights as $item) {
            if (! is_array($item)) {
                continue;
            }

            $parsed[] = [
                'category' => (string) ($item['category'] ?? 'data_action'),
                'title' => (string) ($item['title'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'severity' => (string) ($item['severity'] ?? 'info'),
                'related_column' => isset($item['related_column']) && is_string($item['related_column'])
                    ? $item['related_column']
                    : null,
                'business_impact' => (string) ($item['business_impact'] ?? ''),
            ];

            if (count($parsed) >= $maxInsights) {
                break;
            }
        }

        return [
            'insights' => $parsed,
            'executive_summary' => (string) ($raw['executive_summary'] ?? ''),
        ];
    }
}
