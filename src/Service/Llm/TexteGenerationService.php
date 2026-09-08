<?php

namespace App\Service\Llm;

/**
 * Appel 1 — Génération de texte + questions (cf. Product Specification §3).
 *
 * Aucune donnée sur l'enfant n'est transmise ici : cet appel ne dépend que
 * du niveau visé et de la notion ciblée.
 */
class TexteGenerationService
{
    /**
     * @param array<int, string> $niveauVocabulaire repère de vocabulaire par niveau (config/services.yaml)
     */
    public function __construct(
        private readonly ClaudeClient $claudeClient,
        private readonly array $niveauVocabulaire,
    ) {
    }

    /**
     * @return array{titre: string, texte: string, questions: list<array<string, mixed>>}
     */
    public function genererTexteEtQuestions(int $niveauVise, string $notionCiblee = 'mixte (littérale et inférentielle)'): array
    {
        $repereVocabulaire = $this->niveauVocabulaire[$niveauVise] ?? $this->niveauVocabulaire[3];

        $prompt = <<<PROMPT
            Tu écris un court texte de lecture pour un enfant de CE2 francophone (8-9 ans), dans le cadre
            d'une application d'entraînement à la compréhension de lecture.

            Niveau visé : {$niveauVise} sur 6.
            Repère de vocabulaire et de longueur de phrase à respecter : {$repereVocabulaire}.
            Notion à cibler dans les questions : {$notionCiblee}.

            Consignes :
            - Ton naturel et bienveillant, jamais artificiellement simpliste.
            - Aucun sujet sensible ou effrayant.
            - Propose 1 à 2 questions de compréhension, en mélangeant QCM (avec 3 choix) et réponse rédigée
              ("avec tes propres mots").
            - Pour chaque question, indique la réponse attendue (QCM) ou les critères d'évaluation attendus
              (réponse rédigée) — ce champ sert à l'analyse automatique de la réponse de l'enfant, il n'est
              jamais montré à l'enfant.
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
                'titre' => ['type' => 'string'],
                'texte' => ['type' => 'string'],
                'questions' => [
                    'type' => 'array',
                    // minItems/maxItems non supportés par l'API pour les sorties structurées
                    // (cf. doc Claude Developer Platform) — la contrainte "1 à 2 questions" est
                    // donc portée par le prompt uniquement, pas par le schéma.
                    'description' => 'Toujours 1 ou 2 questions, jamais plus, jamais zéro.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['qcm', 'redigee']],
                            'enonce' => ['type' => 'string'],
                            'choix' => ['type' => ['array', 'null'], 'items' => ['type' => 'string']],
                            'reponse_attendue_ou_criteres' => ['type' => 'string'],
                        ],
                        'required' => ['type', 'enonce', 'choix', 'reponse_attendue_ou_criteres'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['titre', 'texte', 'questions'],
            'additionalProperties' => false,
        ];
    }
}
