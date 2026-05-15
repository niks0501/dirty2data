<?php

namespace App\Services\AI\Contracts;

interface BusinessInsightProvider
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function generate(array $context): array;

    public function providerName(): string;

    public function modelName(): string;
}
