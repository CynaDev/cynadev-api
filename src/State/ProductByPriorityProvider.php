<?php

namespace App\State;

use App\Repository\ProductRepository;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

class ProductByPriorityProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository $productRepository
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->productRepository->findByPriorite();
    }
}
