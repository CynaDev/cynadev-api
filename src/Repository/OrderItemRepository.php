<?php
namespace App\Repository;

use App\Config\Statuses_enums;
use App\Entity\OrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    public function getBestSellingProduct(): ?array
    {
        $result = $this->createQueryBuilder('oi')
            ->select('p.id, p.name, SUM(oi.quantity) as totalQuantity, SUM(o.totalTtc) as totalRevenue')
            ->join('oi.product', 'p')
            ->join('oi.order', 'o')
            ->where('o.status != :status')
            ->setParameter('status', Statuses_enums::Annulee)
            ->groupBy('p.id, p.name')
            ->orderBy('totalQuantity', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$result) return null;

        return [
            'id'            => $result['id'],
            'name'          => $result['name'],
            'totalQuantity' => (int) $result['totalQuantity'],
            'totalRevenue'  => (float) $result['totalRevenue'],
        ];
    }

    public function getTopProducts(int $limit = 5): array
    {
        $rows = $this->createQueryBuilder('oi')
            ->select('p.id, p.name, SUM(oi.quantity) as totalQuantity, SUM(o.totalTtc) as totalRevenue')
            ->join('oi.product', 'p')
            ->join('oi.order', 'o')
            ->where('o.status != :status')
            ->setParameter('status', Statuses_enums::Annulee)
            ->groupBy('p.id, p.name')
            ->orderBy('totalRevenue', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(fn($r) => [
            'id'            => $r['id'],
            'name'          => $r['name'],
            'totalQuantity' => (int) $r['totalQuantity'],
            'totalRevenue'  => (float) $r['totalRevenue'],
        ], $rows);
    }
}