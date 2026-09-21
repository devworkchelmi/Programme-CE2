<?php

namespace App\Entity\Enum;

/**
 * Les trois formats d'exercice de la partie « Orthographe » : texte à trous (mot manquant
 * à écrire), correction (une phrase fautive à réécrire correctement) et choix de mot (QCM
 * parmi plusieurs propositions). Le contenu vient d'une banque de règles/mots fixe
 * (cf. BanqueExercicesService), pas d'une génération par IA — donc pas de relecture
 * adulte nécessaire avant que l'enfant y accède, contrairement à la compréhension de texte.
 */
enum TypeExerciceOrthographe: string
{
    case TEXTE_A_TROUS = 'texte_a_trous';
    case CORRECTION = 'correction';
    case CHOIX_MOT = 'choix_mot';
}
