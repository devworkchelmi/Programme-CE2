<?php

namespace App\Entity\Enum;

enum StatutRelecture: string
{
    case EN_ATTENTE = 'en_attente';
    case VALIDE = 'valide';
    case MODIFIE = 'modifie';
    case REJETE = 'rejete';
}
