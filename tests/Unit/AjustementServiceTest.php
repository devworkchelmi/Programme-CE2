<?php

namespace App\Tests\Unit;

use App\Service\AjustementService;
use PHPUnit\Framework\TestCase;

/**
 * Cf. Product Specification §5.
 */
class AjustementServiceTest extends TestCase
{
    private AjustementService $service;

    protected function setUp(): void
    {
        $this->service = new AjustementService();
    }

    public function testMonteDeNiveauSiLesDeuxDernieresSessionsSontReussiesA80Pourcent(): void
    {
        $resultat = $this->service->calculer(3, [0.9, 0.85, 0.2]);

        self::assertSame(4, $resultat->niveauApres);
        self::assertSame('hausse_80', $resultat->regleAppliquee);
    }

    public function testDescendDeNiveauSiLaDerniereSessionEstReussieAMoinsDe40Pourcent(): void
    {
        $resultat = $this->service->calculer(3, [0.3]);

        self::assertSame(2, $resultat->niveauApres);
        self::assertSame('baisse_40', $resultat->regleAppliquee);
    }

    public function testNeChangePasSiLesResultatsSontEntreDeux(): void
    {
        $resultat = $this->service->calculer(3, [0.6, 0.9]);

        self::assertSame(3, $resultat->niveauApres);
        self::assertSame('inchange', $resultat->regleAppliquee);
    }

    public function testNeDepassePasLeNiveauMaximum(): void
    {
        $resultat = $this->service->calculer(6, [1.0, 1.0]);

        self::assertSame(6, $resultat->niveauApres);
    }

    public function testNeDescendPasSousLeNiveauMinimum(): void
    {
        $resultat = $this->service->calculer(1, [0.1]);

        self::assertSame(1, $resultat->niveauApres);
    }

    public function testResteInchangeSansHistorique(): void
    {
        $resultat = $this->service->calculer(3, []);

        self::assertSame(3, $resultat->niveauApres);
        self::assertSame('inchange', $resultat->regleAppliquee);
    }
}
