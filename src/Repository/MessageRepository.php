<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Find messages for a specific group with eager loading
     *
     * @return Message[] Returns an array of Message objects
     */
    public function findGroupMessages(int $groupId, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('u', 'g')  // Eager loading - zapobiega N+1
            ->join('m.user', 'u')
            ->join('m.group', 'g')
            ->where('m.group = :groupId')
            ->setParameter('groupId', $groupId)
            ->orderBy('m.createdAt', 'DESC')  // Najnowsze najpierw
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }
}
