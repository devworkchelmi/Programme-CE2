<?php

namespace App\Service;

use App\Service\Dto\ResultatTexteDiagnostic;

/**
 * Calcul du niveau de départ à partir du diagnostic initial (cf. Product Specification §4).
 *
 * Règle volontairement simple (pas de modèle dédié, cf. Validation/Recherche §4) :
 * le niveau de départ correspond au dernier texte réussi majoritairement (≥ 50 %
 * de bonnes réponses), parmi des textes de complexité croissante.
 */
class DiagnosticService
{
    /**
     * En dessous de ce nombre de textes complétés, le diagnostic est jugé non fiable
     * et doit être redemandé à la session suivante plutôt que de fixer un niveau au hasard.
     */
    public const MINIMUM_TEXTES_REQUIS = 2;

    public const NIVEAU_MIN = 1;
    public const NIVEAU_MAX = 6;

    private const SEUIL_REUSSITE = 0.5;

    /**
     * @param list<ResultatTexteDiagnostic> $resultats dans l'ordre de complexité croissante
     *
     * @return int|null le niveau de départ, ou null si le diagnostic doit être redemandé
     */
    public function calculerNiveauDepart(array $resultats): ?int
    {
        if (count($resultats) < self::MINIMUM_TEXTES_REQUIS) {
            return null;
        }

        $niveauDepart = self::NIVEAU_MIN;

        foreach ($resultats as $resultat) {
            if ($resultat->tauxReussite >= self::SEUIL_REUSSITE) {
                $niveauDepart = $resultat->niveau;
            }
        }

        return max(self::NIVEAU_MIN, min(self::NIVEAU_MAX, $niveauDepart));
    }
}
