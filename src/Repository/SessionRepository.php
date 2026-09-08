<?php

namespace App\Repository;

use App\Entity\Enfant;
use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    /**
     * Les N dernières sessions JOUEES d'un enfant, les plus récentes en premier —
     * utilisé par AjustementService (cf. Product Specification §5).
     *
     * @return list<Session>
     */
    public function lesPlusRecentesJouees(Enfant $enfant, int $limite): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.enfant = :enfant')
            ->andWhere('s.statut = :statut')
            ->setParameter('enfant', $enfant)
            ->setParameter('statut', \App\Entity\Enum\StatutSession::JOUEE)
            ->orderBy('s.date', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Les N dernières sessions d'un enfant, tous statuts confondus (y compris en attente
     * de relecture) — utilisé pour l'affichage de la liste côté suivi adulte, à ne pas
     * confondre avec lesPlusRecentesJouees() qui sert au calcul de l'ajustement de niveau.
     *
     * @return list<Session>
     */
    public function toutesRecentes(Enfant $enfant, int $limite): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.enfant = :enfant')
            ->setParameter('enfant', $enfant)
            ->orderBy('s.date', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
