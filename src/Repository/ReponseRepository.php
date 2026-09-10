<?php

namespace App\Repository;

use App\Entity\Enfant;
use App\Entity\Reponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reponse>
 */
class ReponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reponse::class);
    }

    /**
     * Réponses dont le type d'erreur proposé par l'IA n'a pas encore été confirmé par
     * l'adulte (cf. Product Specification §2.5), les plus récentes en premier.
     *
     * @return list<Reponse>
     */
    public function aConfirmer(Enfant $enfant, int $limite = 20): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.question', 'q')
            ->join('q.texteGenere', 't')
            ->join('t.session', 's')
            ->andWhere('s.enfant = :enfant')
            ->andWhere('r.typeErreurPropose IS NOT NULL')
            ->andWhere('r.typeErreurConfirme IS NULL')
            ->setParameter('enfant', $enfant)
            ->orderBy('s.date', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Réponses rédigées comportant au moins une correction orthographique, les plus
     * récentes en premier — alimente le récapitulatif d'orthographe du suivi adulte.
     *
     * @return list<Reponse>
     */
    public function avecCorrectionsOrthographe(Enfant $enfant, int $limite = 10): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.question', 'q')
            ->join('q.texteGenere', 't')
            ->join('t.session', 's')
            ->andWhere('s.enfant = :enfant')
            ->andWhere('r.correctionsOrthographe IS NOT NULL')
            ->setParameter('enfant', $enfant)
            ->orderBy('s.date', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
