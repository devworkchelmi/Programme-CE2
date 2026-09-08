<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Un seul compte adulte pour le MVP (cf. Product Specification §0) — pas d'écran
 * d'inscription, on le crée en ligne de commande :
 *
 *   php bin/console app:creer-adulte parent@example.com
 */
#[AsCommand(name: 'app:creer-adulte', description: 'Crée le compte adulte (un seul pour le MVP)')]
class CreerCompteAdulteCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Adresse email de connexion');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $motDePasse = $io->askHidden('Mot de passe');
        $confirmation = $io->askHidden('Confirme le mot de passe');

        if ($motDePasse !== $confirmation) {
            $io->error('Les deux mots de passe ne correspondent pas.');

            return Command::FAILURE;
        }

        $user = new User($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $motDePasse));

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Compte adulte créé pour %s.', $email));

        return Command::SUCCESS;
    }
}
