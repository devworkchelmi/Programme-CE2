<?php

namespace App\Service\Llm;

/**
 * Contrat commun au client réel (ClaudeClient) et au client de démonstration
 * (FakeClaudeClient) — permet de dérouler tout le parcours sans consommer de
 * crédit API, en basculant APP_LLM_FAKE dans .env.local.
 */
interface ClaudeClientInterface
{
    /**
     * @param array<string, mixed> $jsonSchema
     *
     * @return array<string, mixed>
     */
    public function demanderJson(string $prompt, array $jsonSchema, int $maxTokens = 1024): array;
}
