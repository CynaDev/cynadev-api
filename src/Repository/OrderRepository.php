<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function getOrdersTotalPrice(): float
    {
        return (float) $this->createQueryBuilder('o')
            ->select('SUM(o.totalTtc)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // src/Repository/OrderRepository.php
    public function getAverageOrderTotal(): float
    {
        $result = $this->createQueryBuilder('o')
            ->select('AVG(o.totalTtc) as avg_total')
            ->where('o.status = :status')
            ->setParameter('status', 'Payee')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? round((float) $result, 2) : 0.0;
    }

    //    /**
    //     * @return Order[] Returns an array of Order objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Order
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
