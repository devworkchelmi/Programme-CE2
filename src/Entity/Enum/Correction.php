<?php

namespace App\Entity\Enum;

enum Correction: string
{
    case OUI = 'oui';
    case NON = 'non';
    case PARTIEL = 'partiel';

    /**
     * Poids utilisé par AjustementService pour calculer le taux de réussite
     * d'une session : une réponse "partielle" compte pour une demie (cf. Product Specification §5).
     */
    public function poids(): float
    {
        return match ($this) {
            self::OUI => 1.0,
            self::PARTIEL => 0.5,
            self::NON => 0.0,
        };
    }
}
