<?php

namespace App\Controller;

use App\Config\Statuses_enums;
use App\Entity\Order;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class CheckoutConfirmController extends AbstractController
{
    public function __construct(
        private StripeClient $stripe,
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private CartRepository $cartRepository,
        private CartItemRepository $cartItemRepository,
    ) {}

    #[Route('/api/checkout-sessions/{sessionId}/confirm', methods: ['POST'])]
    public function __invoke(string $sessionId): JsonResponse
    {
        // Vérification côté Stripe
        $session = $this->stripe->checkout->sessions->retrieve($sessionId);

        if ($session->payment_status !== 'paid') {
            return new JsonResponse(['error' => 'Paiement non confirmé.'], 400);
        }

        $userId = $session->metadata->user_id ?? null;
        $cartId = $session->metadata->cart_id ?? null;

        if (!$userId || !$cartId) {
            return new JsonResponse(['error' => 'Métadonnées manquantes.'], 400);
        }

        $user = $this->userRepository->find((int) $userId);
        $cart = $this->cartRepository->find((int) $cartId);

        if (!$user || !$cart) {
            return new JsonResponse(['error' => 'Utilisateur ou panier introuvable.'], 404);
        }

        $cartItems = $this->cartItemRepository->findBy(['cart' => $cart]);

        if (empty($cartItems)) {
            return new JsonResponse(['status' => 'already_processed']);
        }

        $totalTtc = array_reduce($cartItems, fn(float $carry, $item) =>
            $carry + ($item->getQuantity() * (float) $item->getUnitPrice()), 0.0
        );
        $totalHt = round($totalTtc / 1.2, 2);

        $order = new Order();
        $order->setUser($user);
        $order->setTotalHt((string) $totalHt);
        $order->setTotalTtc((string) $totalTtc);
        $order->setStatus(Statuses_enums::Payee);
        $order->setDateCommande(new \DateTime());

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProductPlan()?->getProduct();
            if ($product) {
                $order->addProduct($product);
            }
        }

        $this->em->persist($order);

        foreach ($cartItems as $cartItem) {
            $this->em->remove($cartItem);
        }

        $this->em->flush();

        return new JsonResponse(['status' => 'created', 'orderId' => $order->getId()]);
    }
}
