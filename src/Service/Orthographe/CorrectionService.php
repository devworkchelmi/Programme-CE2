<?php

namespace App\Service\Orthographe;

use App\Entity\Enum\Correction;
use App\Entity\Enum\TypeExerciceOrthographe;
use App\Entity\ExerciceOrthographe;

/**
 * Correction déterministe des 3 formats d'exercice — pas d'appel LLM ici, contrairement
 * à AnalyseReponseService côté compréhension : le contenu vient d'une banque fixe
 * (BanqueExercicesService), la bonne réponse est donc déjà connue avec certitude.
 */
class CorrectionService
{
    /**
     * @param list<string> $reponseDonnee un seul élément : le mot tapé/choisi pour
     *                                    texte_a_trous/choix_mot, la phrase entière
     *                                    retapée pour correction
     */
    public function corriger(ExerciceOrthographe $exercice, array $reponseDonnee): Correction
    {
        $contenu = $exercice->getContenu();
        $donnee = $this->normaliser($reponseDonnee[0] ?? '');

        $attendu = match ($exercice->getType()) {
            TypeExerciceOrthographe::TEXTE_A_TROUS, TypeExerciceOrthographe::CHOIX_MOT => $this->normaliser((string) $contenu['reponse']),
            TypeExerciceOrthographe::CORRECTION => $this->normaliser((string) $contenu['phrase_correcte']),
        };

        return $donnee === $attendu ? Correction::OUI : Correction::NON;
    }

    /**
     * Compare sans tenir compte de la casse ni des espaces superflus — mais en gardant
     * les accents, qui distinguent justement souvent deux homophones (a / à, ou / où).
     */
    private function normaliser(string $texte): string
    {
        $texte = mb_strtolower(trim($texte), 'UTF-8');
        $texte = preg_replace('/\s+/u', ' ', $texte) ?? $texte;

        return rtrim($texte, ".!? \t\n");
    }
}
