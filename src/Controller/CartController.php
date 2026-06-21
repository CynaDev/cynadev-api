<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use App\Repository\ProductPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use App\Repository\OrderRepository;

class CartController extends AbstractController
{

    #[Route('/api/carts/user/{id}', methods: ['GET'])]
    public function getCartByUser(int $id, CartRepository $cartRepo, SerializerInterface $serializer): JsonResponse
    {
        $cart = $cartRepo->findOneBy(['user' => $id]);
        if (!$cart) {
            return new JsonResponse(['error' => 'Panier non trouvé pour cet utilisateur'], 404);
        }

        $data = $serializer->serialize($cart, 'json');
        return new JsonResponse($data, 200, [], true);
    }

    #[Route('/api/carts/{id}/items', methods: ['GET'])]
    public function getCartItems(int $id, CartRepository $cartRepo, CartItemRepository $cartItemRepo, SerializerInterface $serializer): JsonResponse
    {
        $cart = $cartRepo->find($id);
        if (!$cart) {
            return new JsonResponse(['error' => 'Panier non trouvé'], 404);
        }

        $items = $cartItemRepo->findBy(['cart' => $id]);

        $data = $serializer->serialize($items, 'json', ['groups' => ['cart:items']]);

        return new JsonResponse($data, 200, [], true);
    }

    #[Route('/api/carts/{id}/items', methods: ['POST'])]
    public function addCartItem(int $id, Request $request, CartRepository $cartRepo, CartItemRepository $cartItemRepo, ProductPlanRepository $planRepo, EntityManagerInterface $em, SerializerInterface $serializer): JsonResponse
    {
        $cart = $cartRepo->find($id);
        if (!$cart) {
            return new JsonResponse(['error' => 'Panier non trouvé'], 404);
        }

        $body = json_decode($request->getContent(), true);
        $productPlanId = $body['productPlanId'] ?? null;
        $quantity = $body['quantity'] ?? 1;

        if (!$productPlanId) {
            return new JsonResponse(['error' => 'productPlanId requis'], 400);
        }

        $plan = $planRepo->find($productPlanId);
        if (!$plan) {
            return new JsonResponse(['error' => 'Plan introuvable'], 404);
        }

        // Si l'item existe déjà pour ce plan, on incrémente la quantité
        $existingItem = $cartItemRepo->findOneBy(['cart' => $cart, 'productPlan' => $plan]);
        if ($existingItem) {
            $existingItem->setQuantity($existingItem->getQuantity() + $quantity);
            $em->flush();
            $data = $serializer->serialize($existingItem, 'json', ['groups' => ['cart:items']]);
            return new JsonResponse($data, 200, [], true);
        }

        $item = new CartItem();
        $item->setCart($cart);
        $item->setProductPlan($plan);
        $item->setQuantity($quantity);
        $item->setUnitPrice($plan->getPrice());

        $em->persist($item);
        $em->flush();

        $data = $serializer->serialize($item, 'json', ['groups' => ['cart:items']]);
        return new JsonResponse($data, 201, [], true);
    }

    #[Route('/api/carts/average-cost', methods: ['GET'], priority: 10)]
    public function getAverageOrderCost(OrderRepository $orderRepo): JsonResponse
    {
        return new JsonResponse([
            'average_cost' => $orderRepo->getAverageOrderTotal()
        ]);
    }
}
