<?php

namespace App\Service;

use App\Entity\Session;
use App\Service\Dto\AjustementResultat;

/**
 * Règle d'ajustement de la difficulté entre sessions (cf. Product Specification §5).
 *
 * Volontairement une règle à état simple plutôt qu'un modèle dédié — cohérent avec
 * le délai de 2 semaines (cf. Validation/Recherche §4). À affiner une fois des
 * données d'usage réelles disponibles (brief, section 18).
 */
class AjustementService
{
    private const SEUIL_HAUSSE = 0.8;
    private const SEUIL_BAISSE = 0.4;

    public const NIVEAU_MIN = 1;
    public const NIVEAU_MAX = 6;

    /**
     * @param list<float> $tauxReussiteRecents taux de réussite des dernières sessions JOUEES,
     *                                          la plus récente en premier (au moins l'élément [0])
     */
    public function calculer(int $niveauActuel, array $tauxReussiteRecents): AjustementResultat
    {
        if ([] === $tauxReussiteRecents) {
            return new AjustementResultat($niveauActuel, $niveauActuel, 'inchange');
        }

        if (
            count($tauxReussiteRecents) >= 2
            && $tauxReussiteRecents[0] >= self::SEUIL_HAUSSE
            && $tauxReussiteRecents[1] >= self::SEUIL_HAUSSE
        ) {
            $niveauApres = min(self::NIVEAU_MAX, $niveauActuel + 1);

            return new AjustementResultat($niveauActuel, $niveauApres, 'hausse_80');
        }

        if ($tauxReussiteRecents[0] < self::SEUIL_BAISSE) {
            $niveauApres = max(self::NIVEAU_MIN, $niveauActuel - 1);

            return new AjustementResultat($niveauActuel, $niveauApres, 'baisse_40');
        }

        return new AjustementResultat($niveauActuel, $niveauActuel, 'inchange');
    }

    /**
     * Taux de réussite d'une session : moyenne pondérée des réponses
     * (une réponse "partielle" comptant pour une demie — cf. Product Specification §5).
     */
    public function tauxReussite(Session $session): float
    {
        $reponses = array_filter(
            array_map(static fn ($question) => $question->getReponse(), $session->getTexteGenere()?->getQuestions()->toArray() ?? []),
        );

        if ([] === $reponses) {
            return 0.0;
        }

        $poids = array_map(
            static fn ($reponse) => $reponse->getCorrecte()?->poids() ?? 0.0,
            $reponses,
        );

        return array_sum($poids) / count($poids);
    }
}
