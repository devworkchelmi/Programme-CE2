<?php

namespace App\Service\Llm;

/**
 * Appel 2 — Analyse de la réponse de l'enfant (cf. Product Specification §3).
 *
 * Le type d'erreur retourné est une PROPOSITION, jamais un verdict définitif
 * (cf. Validation/Recherche §4) : il reste "à confirmer" tant que l'adulte
 * ne l'a pas validé sur l'écran de suivi.
 */
class AnalyseReponseService
{
    public function __construct(private readonly ClaudeClient $claudeClient)
    {
    }

    /**
     * @return array{correcte: string, type_erreur_propose: ?string, justification_courte: string}
     */
    public function analyser(
        string $texte,
        string $enonceQuestion,
        string $reponseAttendueOuCriteres,
        string $reponseEnfant,
    ): array {
        $prompt = <<<PROMPT
            Voici un texte de lecture destiné à un enfant de CE2, une question posée sur ce texte,
            la réponse attendue (ou les critères d'évaluation), et la réponse donnée par l'enfant.

            Texte :
            {$texte}

            Question : {$enonceQuestion}
            Réponse attendue ou critères : {$reponseAttendueOuCriteres}
            Réponse de l'enfant : {$reponseEnfant}

            Évalue si la réponse est correcte, incorrecte, ou partiellement correcte.
            Si elle n'est pas totalement correcte, propose une hypothèse sur le type d'erreur, parmi :
            - "lecture_attention" : l'enfant a mal lu ou n'a pas relu le texte
            - "litterale" : l'enfant n'a pas retrouvé une information explicitement présente dans le texte
            - "inference" : l'enfant n'a pas réussi à déduire une information implicite ("lire entre les lignes")
            Reste prudent : c'est une hypothèse, pas un verdict — elle sera confirmée ou corrigée par un adulte.
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
                'correcte' => ['type' => 'string', 'enum' => ['oui', 'non', 'partiel']],
                'type_erreur_propose' => [
                    'type' => ['string', 'null'],
                    'enum' => ['lecture_attention', 'litterale', 'inference', null],
                ],
                'justification_courte' => ['type' => 'string'],
            ],
            'required' => ['correcte', 'type_erreur_propose', 'justification_courte'],
            'additionalProperties' => false,
        ];
    }
}
