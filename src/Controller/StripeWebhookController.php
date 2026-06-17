<?php

namespace App\Controller;

use App\Config\Statuses_enums;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\StockRepository;

class StripeWebhookController
{
    public function __construct(
        private string $webhookSecret,
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private CartRepository $cartRepository,
        private CartItemRepository $cartItemRepository,
        private StockRepository $stockRepository,
        private OrderRepository $orderRepository,
        private StripeClient $stripe,
        private EmailService $emailService,
    ) {
    }

    #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
        } catch (SignatureVerificationException) {
            return new Response('Invalid signature', 400);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            default => null,
        };

        return new Response('OK');
    }

    // ── Paiement initial (one-shot OU première échéance subscription) ──
    private function handleCheckoutCompleted(object $session): void
    {
        // Pour les subscriptions, le premier invoice.payment_succeeded
        // arrive juste après — on laisse handleInvoicePaymentSucceeded gérer.
        // On traite ici uniquement le mode "payment" one-shot.
        if ($session->mode === 'subscription') {
            // Sauvegarder la subscription_id sur le panier/user pour la retrouver plus tard
            // La création de l'Order se fait dans invoice.payment_succeeded
            return;
        }

        $userId = $session->metadata->user_id ?? null;
        $cartId = $session->metadata->cart_id ?? null;

        if (!$userId || !$cartId)
            return;

        $user = $this->userRepository->find((int) $userId);
        $cart = $this->cartRepository->find((int) $cartId);
        if (!$user || !$cart)
            return;

        $cartItems = $this->cartItemRepository->findBy(['cart' => $cart]);
        if (empty($cartItems))
            return;

        $this->createOrderFromCartItems($user, $cartItems, null);

        foreach ($cartItems as $cartItem) {
            $this->em->remove($cartItem);
        }
        $this->em->flush();
    }

    // ── Renouvellement automatique (subscription mensuelle/annuelle) ──
    private function handleInvoicePaymentSucceeded(object $invoice): void
    {
        if (empty($invoice->subscription))
            return;

        $subscriptionId = $invoice->subscription;
        $customerId = $invoice->customer;

        $user = $this->userRepository->findOneBy(['stripeCustomerId' => $customerId]);
        if (!$user)
            return;

        // Éviter les doublons
        $periodEnd = (new \DateTime())->setTimestamp($invoice->period_end);
        $existing = $this->orderRepository->findOneBy([
            'stripeSubscriptionId' => $subscriptionId,
            'dateCommande' => $periodEnd,
        ]);
        if ($existing)
            return;

        // Récupérer la date de fin de période depuis Stripe
        $subscription = $this->stripe->subscriptions->retrieve($subscriptionId);
        $subscriptionEndsAt = (new \DateTime())->setTimestamp($subscription->current_period_end);

        $totalTtc = $invoice->amount_paid / 100;
        $totalHt = round($totalTtc / 1.2, 2);

        $order = new Order();
        $order->setUser($user);
        $order->setTotalHt((string) $totalHt);
        $order->setTotalTtc((string) $totalTtc);
        $order->setStatus(Statuses_enums::Payee);
        $order->setDateCommande(new \DateTime());
        $order->setStripeSubscriptionId($subscriptionId);
        $order->setSubscriptionEndsAt($subscriptionEndsAt); // ← date de fin

        $stripeLines = $invoice->lines->data ?? [];
        foreach ($stripeLines as $line) {
            $stripePriceId = $line->price->id ?? null;
            if (!$stripePriceId)
                continue;

            $productPlan = $this->em->getRepository(\App\Entity\ProductPlan::class)
                ->findOneBy(['stripePriceId' => $stripePriceId]);

            if (!$productPlan)
                continue;

            $orderItem = new OrderItem();
            $orderItem->setProduct($productPlan->getProduct());
            $orderItem->setProductPlan($productPlan);
            $orderItem->setQuantity($line->quantity ?? 1);
            $orderItem->setPrice((string) ($line->amount / 100));
            
            // Capture snapshots
            $product = $productPlan->getProduct();
            if ($product) {
                $orderItem->setProductName($product->getName());
                
                $productSnapshot = [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'description' => $product->getDescription(),
                    'price' => $product->getPrice(),
                    'categoryId' => $product->getCategoryId(),
                    'disponibilite' => $product->getDisponibilite()?->name,
                ];
                $orderItem->setProductSnapshot($productSnapshot);
            }
            
            $productPlanSnapshot = [
                'id' => $productPlan->getId(),
                'name' => $productPlan->getName(),
                'billingCycle' => $productPlan->getBillingCycle(),
                'price' => $productPlan->getPrice(),
                'stripePriceId' => $productPlan->getStripePriceId(),
                'features' => $productPlan->getFeatures(),
            ];
            $orderItem->setProductPlanSnapshot($productPlanSnapshot);
            
            $order->addOrderItem($orderItem);
            $this->em->persist($orderItem);
        }

        // Vider le panier (première facture seulement)
        $subscription = $this->stripe->subscriptions->retrieve($subscriptionId);
        $cartId = $subscription->metadata->cart_id ?? null;
        if ($cartId) {
            $cart = $this->cartRepository->find((int) $cartId);
            $cartItems = $this->cartItemRepository->findBy(['cart' => $cart]);
            foreach ($cartItems as $cartItem) {
                $this->em->remove($cartItem);
            }
        }

        $this->em->persist($order);
        $this->em->flush();
    }

    // ── Subscription annulée (fin de période ou immédiat) ──
    private function handleSubscriptionDeleted(object $subscription): void
    {
        $order = $this->orderRepository->findOneBy([
            'stripeSubscriptionId' => $subscription->id,
        ]);

        if (!$order)
            return;

        $order->setStatus(Statuses_enums::Annulee);
        $this->em->flush();
    }

    // ── Helper commun ──
    private function createOrderFromCartItems(object $user, array $cartItems, ?string $subscriptionId): void
    {
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

        if ($subscriptionId) {
            $order->setStripeSubscriptionId($subscriptionId);
        }

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProductPlan()?->getProduct();
            if (!$product)
                continue;

            $orderItem = new OrderItem();
            $orderItem->setProduct($product);
            $orderItem->setProductPlan($cartItem->getProductPlan());
            $orderItem->setQuantity($cartItem->getQuantity());
            $orderItem->setPrice($cartItem->getUnitPrice());
            
            // Capture snapshots
            $orderItem->setProductName($product->getName());
            
            $productSnapshot = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => $product->getPrice(),
                'categoryId' => $product->getCategoryId(),
                'disponibilite' => $product->getDisponibilite()?->name,
            ];
            $orderItem->setProductSnapshot($productSnapshot);
            
            $productPlan = $cartItem->getProductPlan();
            if ($productPlan) {
                $productPlanSnapshot = [
                    'id' => $productPlan->getId(),
                    'name' => $productPlan->getName(),
                    'billingCycle' => $productPlan->getBillingCycle(),
                    'price' => $productPlan->getPrice(),
                    'stripePriceId' => $productPlan->getStripePriceId(),
                    'features' => $productPlan->getFeatures(),
                ];
                $orderItem->setProductPlanSnapshot($productPlanSnapshot);
            }
            
            $order->addOrderItem($orderItem);
            $this->em->persist($orderItem);

            $stock = $this->stockRepository->findOneBy(['product' => $product]);
            if ($stock !== null) {
                $stock->setQuantite(max(0, $stock->getQuantite() - $cartItem->getQuantity()));
            }
        }

        $this->em->persist($order);
    }
}