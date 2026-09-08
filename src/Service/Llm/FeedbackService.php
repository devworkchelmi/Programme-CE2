<?php

namespace App\Service\Llm;

/**
 * Appel 3 — Génération du feedback (cf. Product Specification §3).
 *
 * Le type d'erreur reste interne : il n'est jamais nommé tel quel dans le texte
 * affiché à l'enfant (cf. §2.3). Le feedback est calibré avec le même repère de
 * vocabulaire que la génération de texte (cf. Validation/Recherche §2, recommandation n°6).
 */
class FeedbackService
{
    /**
     * @param array<int, string> $niveauVocabulaire repère de vocabulaire par niveau (config/services.yaml)
     */
    public function __construct(
        private readonly ClaudeClientInterface $claudeClient,
        private readonly array $niveauVocabulaire,
    ) {
    }

    /**
     * @return array{texte_feedback: string}
     */
    public function genererFeedback(
        int $niveauVise,
        string $texte,
        string $enonceQuestion,
        string $reponseEnfant,
        ?string $typeErreurPropose,
    ): array {
        $repereVocabulaire = $this->niveauVocabulaire[$niveauVise] ?? $this->niveauVocabulaire[3];

        $prompt = <<<PROMPT
            Tu écris un feedback pour un enfant de CE2 (8-9 ans) qui vient de répondre à une question
            de compréhension de lecture, sans avoir totalement réussi.

            Repère de vocabulaire et de longueur de phrase à respecter (même contrainte que pour les
            textes de lecture) : {$repereVocabulaire}.

            Texte :
            {$texte}

            Question : {$enonceQuestion}
            Réponse de l'enfant : {$reponseEnfant}
            Hypothèse interne sur le type d'erreur (ne jamais nommer ce terme à l'enfant) : {$typeErreurPropose}

            Écris un feedback court, encourageant, en langage simple et concret, qui aide l'enfant à
            comprendre son erreur sans jamais utiliser de jargon ("inférence", "compréhension littérale", etc.)
            ni le décourager.
            PROMPT;

        return $this->claudeClient->demanderJson($prompt, $this->schema());
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'texte_feedback' => ['type' => 'string'],
            ],
            'required' => ['texte_feedback'],
            'additionalProperties' => false,
        ];
    }
}
