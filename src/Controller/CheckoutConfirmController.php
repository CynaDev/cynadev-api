<?php

namespace App\Controller;

use App\Config\Statuses_enums;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use App\Repository\StockRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
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
        private StockRepository $stockRepository,
        private EmailService $emailService,
    ) {
    }

    #[Route('/api/checkout-sessions/{sessionId}/confirm', methods: ['POST'])]
    public function __invoke(string $sessionId): JsonResponse
    {
        $session = $this->stripe->checkout->sessions->retrieve($sessionId, [
            'expand' => ['subscription'], // ← AJOUTE pour récupérer la subscription
        ]);

        // ← Pour subscription, le statut est 'unpaid' au moment du confirm
        // On vérifie différemment selon le mode
        if ($session->mode === 'subscription') {
            if ($session->status !== 'complete') {
                return new JsonResponse(['error' => 'Session non complétée.'], 400);
            }
        } else {
            if ($session->payment_status !== 'paid') {
                return new JsonResponse(['error' => 'Paiement non confirmé.'], 400);
            }
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

        // ← Sauvegarder le stripeCustomerId sur l'utilisateur
        if ($session->customer && !$user->getStripeCustomerId()) {
            $user->setStripeCustomerId($session->customer);
        }

        $totalTtc = array_reduce(
            $cartItems,
            fn(float $carry, $item) =>
            $carry + ($item->getQuantity() * (float) $item->getUnitPrice()),
            0.0
        );
        $totalHt = round($totalTtc / 1.2, 2);

        $order = new Order();
        $order->setUser($user);
        $order->setTotalHt((string) $totalHt);
        $order->setTotalTtc((string) $totalTtc);
        $order->setStatus(Statuses_enums::Payee);
        $order->setDateCommande(new \DateTime());

        // ← Sauvegarder la subscription_id si mode subscription
        if ($session->mode === 'subscription' && $session->subscription) {
            $subId = is_string($session->subscription)
                ? $session->subscription
                : $session->subscription->id;
            $order->setStripeSubscriptionId($subId);
        }

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProductPlan()?->getProduct();
            if ($product) {
                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setQuantity($cartItem->getQuantity());
                $orderItem->setPrice($cartItem->getUnitPrice());
                $orderItem->setProductPlan($cartItem->getProductPlan());
                $order->addOrderItem($orderItem);
                $this->em->persist($orderItem);

                $stock = $this->stockRepository->findOneBy(['product' => $product]);
                if ($stock !== null) {
                    $newQty = max(0, $stock->getQuantite() - $cartItem->getQuantity());
                    $stock->setQuantite($newQty);
                }
            }
        }

        $this->em->persist($order);

        // ← Snapshot des items AVANT de les supprimer pour l'email
        $cartItemsSnapshot = array_map(fn($item) => clone $item, $cartItems);

        foreach ($cartItems as $cartItem) {
            $this->em->remove($cartItem);
        }

        $this->em->flush();

        $this->emailService->sendOrderConfirmationEmail($user, $order, $cartItemsSnapshot); // ← snapshot

        return new JsonResponse(['status' => 'created', 'orderId' => $order->getId()]);
    }
}