<?php

namespace App\Service\Anonymisation;

use App\Entity\Enfant;

/**
 * Garde-fou avant tout appel LLM (cf. Product Specification §6 et Validation/Recherche §3) :
 * aucun prénom réel ni donnée identifiante ne doit quitter l'application.
 *
 * Les services Llm\* ne reçoivent jamais l'entité Enfant — seulement des valeurs
 * anonymes (niveau, texte, réponse). Cette classe sert de second filet de sécurité :
 * elle vérifie qu'un contenu sur le point d'être envoyé ne contient pas le prénom réel.
 */
class AnonymisationService
{
    /**
     * @throws \RuntimeException si le prénom réel de l'enfant apparaît dans le texte
     */
    public function verifierAbsenceDonneesIdentifiantes(string $contenuAEnvoyer, Enfant $enfant): void
    {
        $prenom = mb_strtolower(trim($enfant->getPrenomAffichage()));

        if ('' === $prenom) {
            return;
        }

        if (str_contains(mb_strtolower($contenuAEnvoyer), $prenom)) {
            throw new \RuntimeException(
                'Le contenu destiné au LLM contient le prénom réel de l\'enfant — envoi bloqué (cf. Product Specification §6).'
            );
        }
    }
}
