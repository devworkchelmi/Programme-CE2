<?php

namespace App\Service\Llm;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client bas niveau pour l'API Claude (Anthropic), utilisé par les 3 services
 * d'orchestration (TexteGenerationService, AnalyseReponseService, FeedbackService).
 *
 * Utilise les "structured outputs" de l'API (output_config.format en JSON Schema)
 * pour garantir une réponse JSON valide plutôt que de parser du texte libre
 * (cf. architecture technique §1 — vérifié dans la doc Claude Developer Platform).
 */
class ClaudeClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_VERSION = '2023-06-01';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    /**
     * @param array<string, mixed> $jsonSchema
     *
     * @return array<string, mixed>
     */
    public function demanderJson(string $prompt, array $jsonSchema, int $maxTokens = 1024): array
    {
        if ('' === $this->apiKey) {
            throw new \RuntimeException(
                'ANTHROPIC_API_KEY manquant. Ajoute-le dans .env.local (jamais dans .env, cf. architecture technique §3).'
            );
        }

        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'output_config' => [
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => $jsonSchema,
                    ],
                ],
            ],
        ]);

        try {
            $donnees = $response->toArray();
        } catch (HttpExceptionInterface $e) {
            // Le corps de la réponse contient le message d'erreur exact d'Anthropic
            // (bien plus utile que le simple code HTTP pour déboguer, cf. README).
            $corps = $response->getContent(false);

            throw new \RuntimeException(sprintf('Erreur API Claude (%s) : %s', $e->getMessage(), $corps), previous: $e);
        }

        $texte = $donnees['content'][0]['text'] ?? null;

        if (null === $texte) {
            throw new \RuntimeException('Réponse Claude inattendue : aucun contenu texte trouvé dans la réponse.');
        }

        /** @var array<string, mixed> $decode */
        $decode = json_decode($texte, true, 512, JSON_THROW_ON_ERROR);

        return $decode;
    }
}
