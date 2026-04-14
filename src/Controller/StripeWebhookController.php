<?php

namespace App\Controller;

use App\Config\Statuses_enums;
use App\Entity\Order;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController
{
    public function __construct(
        private string $webhookSecret,
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private CartRepository $cartRepository,
        private CartItemRepository $cartItemRepository,
    ) {}

    #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
        } catch (SignatureVerificationException) {
            return new Response('Invalid signature', 400);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            default                      => null,
        };

        return new Response('OK');
    }

    private function handleCheckoutCompleted(object $session): void
    {
        $userId = $session->metadata->user_id ?? null;
        $cartId = $session->metadata->cart_id ?? null;

        if (!$userId || !$cartId) {
            return;
        }

        $user = $this->userRepository->find((int) $userId);
        $cart = $this->cartRepository->find((int) $cartId);

        if (!$user || !$cart) {
            return;
        }

        $cartItems = $this->cartItemRepository->findBy(['cart' => $cart]);

        if (empty($cartItems)) {
            return;
        }

        $totalTtc = array_reduce($cartItems, function (float $carry, $item) {
            return $carry + ($item->getQuantity() * (float) $item->getUnitPrice());
        }, 0.0);

        // TVA à 20 %
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
    }
}
