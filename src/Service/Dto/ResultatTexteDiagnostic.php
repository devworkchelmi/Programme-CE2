<?php

namespace App\Service\Dto;

/**
 * Résultat d'un texte du diagnostic initial, dans l'ordre de complexité croissante
 * (cf. Product Specification §4).
 */
final class ResultatTexteDiagnostic
{
    public function __construct(
        public readonly int $niveau,
        public readonly float $tauxReussite,
    ) {
    }
}
