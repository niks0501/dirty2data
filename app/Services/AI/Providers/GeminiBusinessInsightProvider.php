<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\BusinessInsightProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiBusinessInsightProvider implements BusinessInsightProvider
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function generate(array $context): array
    {
        $apiKey = (string) config('ai.providers.gemini.api_key');

        if ($apiKey === '' || $apiKey === 'your_api_key_here') {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $baseUrl = rtrim((string) config('ai.providers.gemini.base_url'), '/');
        $model = $this->modelName();
        $prompt = $this->prompt()."\n\nDataset context:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

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
            throw new RuntimeException('Gemini business insight request failed.');
        }

        $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');

        if ($text === '') {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        $decoded = json_decode($this->jsonText($text), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini returned invalid JSON.');
        }

        return $decoded;
    }

    public function providerName(): string
    {
        return 'gemini';
    }

    public function modelName(): string
    {
        return (string) config('ai.model', 'gemini-2.5-flash-lite');
    }

    private function prompt(): string
    {
        $path = resource_path('prompts/business-insight-generation.txt');

        if (! is_file($path)) {
            throw new RuntimeException('Business insight generation prompt file is missing.');
        }

        return (string) file_get_contents($path);
    }

    private function jsonText(string $text): string
    {
        $trimmed = trim($text);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }
}
