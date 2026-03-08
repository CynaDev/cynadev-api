<?php

namespace App\Repository;

use App\Entity\Token;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Token>
 */
class TokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Token::class);
    }

    /**
     * Invalide (supprime ou marque comme utilisé) les anciens tokens d'un type donné pour un user
     */
    public function invalidateUserTokensByType(User $user, string $type): int
{
    return $this->createQueryBuilder('t')
        ->update()
        ->set('t.isUsed', ':true')
        ->set('t.usedAt', ':now')
        ->where('t.user = :user')
        ->andWhere('t.type = :type')
        ->andWhere('t.isUsed = :false') 
        ->setParameter('true', true)
        ->setParameter('now', new \DateTime())
        ->setParameter('user', $user)
        ->setParameter('type', $type)
        ->setParameter('false', false)
        ->getQuery()
        ->execute();
}

    /**
     * Trouve un token valide par sa chaîne et son type
     */
    public function findValidToken(string $tokenValue, string $type): ?Token
    {
        return $this->createQueryBuilder('t')
            ->where('t.token = :token')
            ->andWhere('t.type = :type')
            ->andWhere('t.isUsed = :false')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('token', $tokenValue)
            ->setParameter('type', $type)
            ->setParameter('false', false)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte le nombre de tokens actifs pour un user et un type
     */
    public function countActiveTokensByType(User $user, string $type): int
    {
        return $this->createQueryBuilder('t')
            ->select('count(t.id)')
            ->where('t.user = :user')
            ->andWhere('t.type = :type')
            ->andWhere('t.isUsed = :false')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->setParameter('false', false)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Supprime les tokens expirés (Nettoyage)
     */
    public function deleteExpiredTokens(): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.expiresAt <= :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }
}