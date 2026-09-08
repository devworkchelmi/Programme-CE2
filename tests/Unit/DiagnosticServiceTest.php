<?php

namespace App\Tests\Unit;

use App\Service\Dto\ResultatTexteDiagnostic;
use App\Service\DiagnosticService;
use PHPUnit\Framework\TestCase;

/**
 * Cf. Product Specification §4.
 */
class DiagnosticServiceTest extends TestCase
{
    private DiagnosticService $service;

    protected function setUp(): void
    {
        $this->service = new DiagnosticService();
    }

    public function testRedemandeLeDiagnosticSiMoinsDeDeuxTextesCompletes(): void
    {
        $resultats = [new ResultatTexteDiagnostic(niveau: 1, tauxReussite: 1.0)];

        self::assertNull($this->service->calculerNiveauDepart($resultats));
    }

    public function testNiveauDepartCorrespondAuDernierTexteReussiMajoritairement(): void
    {
        $resultats = [
            new ResultatTexteDiagnostic(niveau: 1, tauxReussite: 1.0),
            new ResultatTexteDiagnostic(niveau: 2, tauxReussite: 0.5),
            new ResultatTexteDiagnostic(niveau: 3, tauxReussite: 0.0),
        ];

        self::assertSame(2, $this->service->calculerNiveauDepart($resultats));
    }

    public function testNiveauDepartPlafonneA6(): void
    {
        $resultats = [
            new ResultatTexteDiagnostic(niveau: 5, tauxReussite: 1.0),
            new ResultatTexteDiagnostic(niveau: 6, tauxReussite: 1.0),
        ];

        self::assertSame(6, $this->service->calculerNiveauDepart($resultats));
    }

    public function testNiveauDepartPlancherA1SiAucunTexteReussi(): void
    {
        $resultats = [
            new ResultatTexteDiagnostic(niveau: 3, tauxReussite: 0.0),
            new ResultatTexteDiagnostic(niveau: 4, tauxReussite: 0.0),
        ];

        self::assertSame(1, $this->service->calculerNiveauDepart($resultats));
    }
}
