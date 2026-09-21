<?php

namespace App\Repository;

use App\Entity\Enfant;
use App\Entity\ExerciceOrthographe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExerciceOrthographe>
 */
class ExerciceOrthographeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExerciceOrthographe::class);
    }

    /**
     * Les N derniers exercices répondus d'un enfant, les plus récents en premier —
     * utilisé pour l'ajustement de niveau (même règle que la compréhension, cf.
     * AjustementService) et pour l'affichage (accueil orthographe, suivi adulte).
     *
     * @return list<ExerciceOrthographe>
     */
    public function lesPlusRecentsRepondus(Enfant $enfant, int $limite): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.enfant = :enfant')
            ->andWhere('e.dateReponse IS NOT NULL')
            ->setParameter('enfant', $enfant)
            ->orderBy('e.dateReponse', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
