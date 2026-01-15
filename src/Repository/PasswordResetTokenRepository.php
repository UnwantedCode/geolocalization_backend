<?php

namespace App\Repository;

use App\Entity\PasswordResetToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function findValidTokenByEmail(string $email): ?PasswordResetToken
    {
        $token = $this->findOneBy(['email' => $email], ['createdAt' => 'DESC']);

        if ($token && $token->isValid()) {
            return $token;
        }

        return null;
    }

    public function deleteExpiredTokens(): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.expiresAt < :now')
            ->orWhere('t.attempts >= 5')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    public function deleteTokensByEmail(string $email): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->execute();
    }
}