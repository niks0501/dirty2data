<?php

namespace App\Services\Datasets;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server HTTP client for the Python FastAPI data processing service.
 *
 * Sends uploaded dataset files for profiling and quality scoring,
 * then returns structured JSON results for Laravel to persist.
 */
class DatasetQualityScoreClient
{
    /**
     * Send an uploaded file to the Python service and return the quality score response.
     *
     * @return array<string, mixed> The decoded JSON response body.
     *
     * @throws \RuntimeException When the Python service is unreachable or returns an error.
     */
    public function score(string $filePath, string $filename): array
    {
        $baseUrl = rtrim((string) config('services.python_data_service.base_url'), '/');
        $timeout = (int) config('services.python_data_service.timeout', 120);
        $authToken = config('services.python_data_service.auth_token');

        if ($baseUrl === '' || $baseUrl === '0') {
            throw new \RuntimeException('Python data service base URL is not configured.');
        }

        try {
            $request = Http::timeout($timeout)
                ->attach('file', fopen($filePath, 'r'), $filename);

            if ($authToken) {
                $request = $request->withToken($authToken);
            }

            $response = $request->post($baseUrl.'/datasets/uploads');
        } catch (ConnectionException $e) {
            Log::error('Python data service unreachable.', [
                'url' => $baseUrl,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('The data processing service is currently unavailable. Please try again later.', 0, $e);
        }

        if (! $response->successful()) {
            $statusCode = $response->status();
            $detail = $response->json('detail') ?? 'Unknown error from processing service.';

            Log::error('Python data service returned an error.', [
                'url' => $baseUrl,
                'status' => $statusCode,
                'detail' => $detail,
            ]);

            $detailStr = is_array($detail) ? json_encode($detail) : (string) $detail;
            throw new \RuntimeException("Processing error ({$statusCode}): {$detailStr}", $statusCode);
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new \RuntimeException('Unexpected response format from data processing service.');
        }

        return $body;
    }

    /**
     * Send profile metrics to the Python service and return the quality score response.
     *
     * @param  array<string, mixed>  $profileMetrics
     * @return array<string, mixed> The decoded JSON response body.
     *
     * @throws \RuntimeException When the Python service is unreachable or returns an error.
     */
    public function scoreFromMetrics(array $profileMetrics, string $scoreType = 'after'): array
    {
        $baseUrl = rtrim((string) config('services.python_data_service.base_url'), '/');
        $timeout = (int) config('services.python_data_service.timeout', 120);
        $authToken = config('services.python_data_service.auth_token');

        if ($baseUrl === '' || $baseUrl === '0') {
            throw new \RuntimeException('Python data service base URL is not configured.');
        }

        try {
            $request = Http::timeout($timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withToken($authToken ?? '');

            $response = $request->post($baseUrl.'/quality-scores', [
                'score_type' => $scoreType,
                'profile_metrics' => $profileMetrics,
            ]);
        } catch (ConnectionException $e) {
            Log::error('Python data service unreachable.', [
                'url' => $baseUrl,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('The data processing service is currently unavailable. Please try again later.', 0, $e);
        }

        if (! $response->successful()) {
            $statusCode = $response->status();
            $detail = $response->json('detail') ?? 'Unknown error from processing service.';

            Log::error('Python data service returned an error.', [
                'url' => $baseUrl,
                'status' => $statusCode,
                'detail' => $detail,
            ]);

            $detailStr = is_array($detail) ? json_encode($detail) : (string) $detail;
            throw new \RuntimeException("Processing error ({$statusCode}): {$detailStr}", $statusCode);
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new \RuntimeException('Unexpected response format from data processing service.');
        }

        return $body;
    }
}
