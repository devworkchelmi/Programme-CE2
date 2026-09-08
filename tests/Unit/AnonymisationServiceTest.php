<?php

namespace App\Tests\Unit;

use App\Entity\Enfant;
use App\Service\Anonymisation\AnonymisationService;
use PHPUnit\Framework\TestCase;

/**
 * Cf. Product Specification §6.
 */
class AnonymisationServiceTest extends TestCase
{
    public function testNeLevePasDExceptionSiLeContenuNeContientPasLePrenom(): void
    {
        $enfant = new Enfant('Léo');
        $service = new AnonymisationService();

        $service->verifierAbsenceDonneesIdentifiantes('Un enfant a lu un texte sur la forêt.', $enfant);

        $this->addToAssertionCount(1);
    }

    public function testLeveUneExceptionSiLeContenuContientLePrenomReel(): void
    {
        $enfant = new Enfant('Léo');
        $service = new AnonymisationService();

        $this->expectException(\RuntimeException::class);

        $service->verifierAbsenceDonneesIdentifiantes('Léo a répondu à la question 2.', $enfant);
    }
}
