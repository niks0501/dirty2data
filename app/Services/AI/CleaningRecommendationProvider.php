<?php

namespace App\Services\AI;

interface CleaningRecommendationProvider
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function recommend(array $context): array;

    public function providerName(): string;

    public function modelName(): string;
}
