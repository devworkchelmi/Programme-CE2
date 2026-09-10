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
    public function __construct(private readonly ClaudeClientInterface $claudeClient)
    {
    }

    /**
     * @param bool $verifierOrthographe uniquement pour les réponses rédigées : un QCM
     *                                  ne fait que reprendre un choix déjà écrit
     *
     * @return array{correcte: string, type_erreur_propose: ?string, justification_courte: string, orthographe: list<array{mot_ecrit: string, correction: string}>}
     */
    public function analyser(
        string $texte,
        string $enonceQuestion,
        string $reponseAttendueOuCriteres,
        string $reponseEnfant,
        bool $verifierOrthographe = false,
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

        if ($verifierOrthographe) {
            $prompt .= <<<PROMPT


                Relève aussi les mots mal orthographiés dans la réponse de l'enfant, dans le champ
                "orthographe" (liste vide s'il n'y en a pas). Règles :
                - Ne signale que de vraies fautes de mots ; ignore la casse, les accents manquants
                  sur les majuscules, la ponctuation absente et les tournures maladroites.
                - 5 mots au maximum, les plus utiles à corriger pour un enfant de CE2.
                - L'orthographe ne doit EN AUCUN CAS influencer ton évaluation de la compréhension :
                  un enfant qui a compris mais écrit mal reste "correcte : oui".
                PROMPT;
        }

        $resultat = $this->claudeClient->demanderJson($prompt, $this->schema($verifierOrthographe));
        $resultat['orthographe'] ??= [];

        return $resultat;
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(bool $avecOrthographe): array
    {
        $proprietes = [
            'correcte' => ['type' => 'string', 'enum' => ['oui', 'non', 'partiel']],
            'type_erreur_propose' => [
                'type' => ['string', 'null'],
                'enum' => ['lecture_attention', 'litterale', 'inference', null],
            ],
            'justification_courte' => ['type' => 'string'],
        ];
        $requis = ['correcte', 'type_erreur_propose', 'justification_courte'];

        if ($avecOrthographe) {
            $proprietes['orthographe'] = [
                'type' => 'array',
                'description' => 'Mots mal orthographiés, liste vide si la réponse est correctement écrite. 5 au maximum.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'mot_ecrit' => ['type' => 'string'],
                        'correction' => ['type' => 'string'],
                    ],
                    'required' => ['mot_ecrit', 'correction'],
                    'additionalProperties' => false,
                ],
            ];
            $requis[] = 'orthographe';
        }

        return [
            'type' => 'object',
            'properties' => $proprietes,
            'required' => $requis,
            'additionalProperties' => false,
        ];
    }
}
