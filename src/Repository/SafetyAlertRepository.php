<?php

namespace App\Repository;

use App\Entity\SafetyAlert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SafetyAlert>
 */
class SafetyAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SafetyAlert::class);
    }

    /**
     * Find safety alerts for a specific group with optional active filter
     *
     * @param int $groupId
     * @param bool|null $activeOnly true = only unresolved, false = only resolved, null = all
     * @return SafetyAlert[]
     */
    public function findGroupAlerts(int $groupId, ?bool $activeOnly = null): array
    {
        $qb = $this->createQueryBuilder('sa')
            ->addSelect('u', 'g', 'l')  // Eager loading - zapobiega N+1
            ->join('sa.user', 'u')
            ->join('sa.group', 'g')
            ->join('sa.location', 'l')
            ->where('sa.group = :groupId')
            ->setParameter('groupId', $groupId)
            ->orderBy('sa.createdAt', 'DESC');  // Najnowsze najpierw

        if ($activeOnly === true) {
            $qb->andWhere('sa.resolved = false');
        } elseif ($activeOnly === false) {
            $qb->andWhere('sa.resolved = true');
        }

        return $qb->getQuery()->getResult();
    }
}
