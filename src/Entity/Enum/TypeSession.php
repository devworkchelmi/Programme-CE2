<?php

namespace App\Entity\Enum;

enum TypeSession: string
{
    case DIAGNOSTIC = 'diagnostic';
    case STANDARD = 'standard';
}
