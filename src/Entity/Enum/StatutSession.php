<?php

namespace App\Entity\Enum;

/**
 * Cf. Product Specification §1 et §3 : une session ne devient jouable
 * par l'enfant qu'une fois son texte VALIDEE par l'adulte.
 */
enum StatutSession: string
{
    case GENEREE = 'generee';
    case EN_ATTENTE_RELECTURE = 'en_attente_relecture';
    case VALIDEE = 'validee';
    case JOUEE = 'jouee';
}
