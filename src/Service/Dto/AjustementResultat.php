<?php

namespace App\Service\Dto;

final class AjustementResultat
{
    public function __construct(
        public readonly int $niveauAvant,
        public readonly int $niveauApres,
        public readonly string $regleAppliquee,
    ) {
    }
}
