<?php

namespace App\Command;

use App\Entity\Enfant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Un seul enfant pour le MVP (cf. Product Specification §0) :
 *
 *   php bin/console app:creer-enfant "Prénom"
 */
#[AsCommand(name: 'app:creer-enfant', description: 'Crée la fiche enfant (une seule pour le MVP)')]
class CreerEnfantCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('prenom', InputArgument::REQUIRED, "Prénom d'affichage — reste local, jamais transmis au LLM");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $enfant = new Enfant($input->getArgument('prenom'));

        $this->em->persist($enfant);
        $this->em->flush();

        $io->success(sprintf('Fiche enfant créée (id %d).', $enfant->getId()));

        return Command::SUCCESS;
    }
}
