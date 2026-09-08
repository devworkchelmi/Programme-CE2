<?php

namespace App\Entity\Enum;

/**
 * Typologie enrichie à 3 catégories (cf. Validation/Recherche §2, recommandation n°2) :
 * plus actionnable que la distinction binaire inattention/incompréhension du brief initial.
 * Reste une information interne — jamais affichée telle quelle à l'enfant (cf. Product Specification §2.3).
 */
enum TypeErreur: string
{
    case LECTURE_ATTENTION = 'lecture_attention';
    case LITTERALE = 'litterale';
    case INFERENCE = 'inference';
}
