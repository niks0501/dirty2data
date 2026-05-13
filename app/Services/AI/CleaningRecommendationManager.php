<?php

namespace App\Services\AI;

use App\Services\Datasets\CleaningRecommendationValidator;
use Illuminate\Support\Facades\Log;

class CleaningRecommendationManager
{
    public function __construct(
        private readonly GeminiCleaningRecommendationProvider $gemini,
        private readonly LocalHeuristicCleaningRecommendationProvider $heuristic,
        private readonly CleaningRecommendationValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $headers
     * @return array{provider: string, model: string, source: string, fallback_reason: string|null, raw_response: array<string, mixed>, recommendations: list<array<string, mixed>>}
     */
    public function recommend(array $context, array $headers): array
    {
        $provider = $this->provider();
        $fallbackReason = null;

        try {
            if ((bool) config('ai.enabled', true) && $provider->providerName() === 'gemini') {
                $raw = $provider->recommend($context);
                $recommendations = $this->validator->validate($raw, $headers, $provider->providerName());

                if ($recommendations !== []) {
                    return [
                        'provider' => $provider->providerName(),
                        'model' => $provider->modelName(),
                        'source' => 'ai',
                        'fallback_reason' => null,
                        'raw_response' => $raw,
                        'recommendations' => $recommendations,
                    ];
                }

                $fallbackReason = 'Gemini returned no supported recommendations.';
            }
        } catch (\Throwable $e) {
            $fallbackReason = $e->getMessage();
            Log::warning('AI cleaning recommendations fell back to local heuristics.', [
                'reason' => $fallbackReason,
            ]);
        }

        $raw = $this->heuristic->recommend($context);

        return [
            'provider' => $this->heuristic->providerName(),
            'model' => $this->heuristic->modelName(),
            'source' => 'heuristic',
            'fallback_reason' => $fallbackReason,
            'raw_response' => $raw,
            'recommendations' => $this->validator->validate($raw, $headers, $this->heuristic->providerName()),
        ];
    }

    private function provider(): CleaningRecommendationProvider
    {
        return match ((string) config('ai.provider', 'gemini')) {
            'gemini' => $this->gemini,
            default => $this->heuristic,
        };
    }
}
