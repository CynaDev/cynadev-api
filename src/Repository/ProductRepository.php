<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Config\Disponibilite_enums;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    //    /**
//     * @return Product[] Returns an array of Product objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

    //    public function findOneBySomeField($value): ?Product
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    public function findAllSorted(?int $categoryId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect(
                'CASE 
            WHEN p.disponibilite = :epuise THEN 2
            WHEN p.priorite IS NOT NULL THEN 0
            ELSE 1
        END AS HIDDEN sortGroup'
            )
            ->setParameter('epuise', Disponibilite_enums::Indisponible)
            ->orderBy('sortGroup', 'ASC')
            ->addOrderBy('p.priorite', 'ASC')
            ->addOrderBy('p.dateAjout', 'DESC');

        if ($categoryId !== null) {
            $qb->where('p.categoryId = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        return $qb->getQuery()->getResult();
    }


    public function findByPriorite(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.priorite IS NOT NULL')
            ->orderBy('p.priorite', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findSortedAll():array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect(
                'CASE 
            WHEN p.disponibilite = :epuise THEN 2
            WHEN p.priorite IS NOT NULL THEN 0
            ELSE 1
        END AS HIDDEN sortGroup'
            )
            ->setParameter('epuise', Disponibilite_enums::Indisponible)
            ->orderBy('sortGroup', 'ASC')
            ->addOrderBy('p.priorite', 'ASC')
            ->addOrderBy('p.dateAjout', 'DESC');
        return $qb->getQuery()->getResult();
    }

}
