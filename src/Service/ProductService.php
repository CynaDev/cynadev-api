<?php

namespace App\Service;

use App\Repository\ProductRepository;

class ProductService
{
    public function __construct(
        private ProductRepository $productRepository
    ) {}

    public function getProductsByPriority(): array
    {
        return $this->productRepository->findByPriorite();
    }

    public function getProductsSorted(?int $categoryId = null): array
    {
        return $this->productRepository->findAllSorted($categoryId);
    }

    public function getProductsAll(): array
    {
        return $this->productRepository->findSortedAll();
    }
}
