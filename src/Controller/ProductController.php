<?php

namespace App\Controller;

use App\Service\ProductService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    public function __construct(
        private ProductService $productService
    ) {}

    #[Route('/api/priority/products', name: 'products_by_priority', methods: ['GET'])]
    public function getByPriority(Request $request): JsonResponse
    {
        $products = $this->productService->getProductsByPriority();

        $data = array_map(fn($p) => [
            'id'            => $p->getId(),
            'name'          => $p->getName(),
            'description'   => $p->getDescription(),
            'price'         => $p->getPrice(),
            'categoryId'    => $p->getCategoryId(),
            'priorite'      => $p->getPriorite(),
            'disponibilite' => $p->getDisponibilite()->value,
            'dateAjout'     => $p->getDateAjout()?->format('Y-m-d'),
        ], $products);

        return $this->json(['member' => $data]);
    }

    #[Route('/api/sorted/products', name: 'products_sorted', methods: ['GET'])]
    public function getSorted(Request $request): JsonResponse
    {
        $categoryId = $request->query->get('categoryId');
        $products = $this->productService->getProductsSorted($categoryId ? (int)$categoryId : null);

        $data = array_map(fn($p) => [
            'id'            => $p->getId(),
            'name'          => $p->getName(),
            'description'   => $p->getDescription(),
            'price'         => $p->getPrice(),
            'categoryId'    => $p->getCategoryId(),
            'priorite'      => $p->getPriorite(),
            'disponibilite' => $p->getDisponibilite()->value,
            'dateAjout'     => $p->getDateAjout()?->format('Y-m-d'),
        ], $products);

        return $this->json(['member' => $data]);
    }

    #[Route('/api/sorted/all', name: 'products_sorted_all', methods: ['GET'])]
    public function getSortedAll(Request $request): JsonResponse
    {
        $products = $this->productService->getProductsAll();

        $data = array_map(fn($p) => [
            'id'            => $p->getId(),
            'name'          => $p->getName(),
            'description'   => $p->getDescription(),
            'price'         => $p->getPrice(),
            'categoryId'    => $p->getCategoryId(),
            'priorite'      => $p->getPriorite(),
            'disponibilite' => $p->getDisponibilite()->value,
            'dateAjout'     => $p->getDateAjout()?->format('Y-m-d'),
        ], $products);

        return $this->json(['member' => $data]);
    }
}
