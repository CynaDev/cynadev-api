<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class OrderController extends AbstractController
{
    #[Route('/api/users/{id}/orders', methods: ['GET'])]
    public function getOrdersByUser(
        int $id,
        UserRepository $userRepository,
        OrderRepository $orderRepository,
        SerializerInterface $serializer,
    ): JsonResponse {
        $user = $userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur introuvable'], 404);
        }

        $orders = $orderRepository->findBy(
            ['user' => $user],
            ['dateCommande' => 'DESC'],
        );

        $data = $serializer->serialize($orders, 'json', ['groups' => ['order:read']]);

        return new JsonResponse($data, 200, [], true);
    }
}
