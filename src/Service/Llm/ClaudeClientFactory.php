<?php

namespace App\Service\Llm;

/**
 * Choisit, au moment de l'instanciation, le client réel ou le client de démonstration
 * selon APP_LLM_FAKE — un alias de service ne suffirait pas, il est résolu à la
 * compilation du conteneur alors que la variable d'environnement l'est à l'exécution.
 */
class ClaudeClientFactory
{
    public function __construct(
        private readonly ClaudeClient $clientReel,
        private readonly FakeClaudeClient $clientDemo,
        private readonly bool $modeDemo,
    ) {
    }

    public function creer(): ClaudeClientInterface
    {
        return $this->modeDemo ? $this->clientDemo : $this->clientReel;
    }
}
