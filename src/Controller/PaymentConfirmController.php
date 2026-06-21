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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class PaymentConfirmController extends AbstractController
{
    public function __construct(
        private StripeClient $stripe,
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private CartRepository $cartRepository,
        private CartItemRepository $cartItemRepository,
        private StockRepository $stockRepository,
        private EmailService $emailService,
    ) {}

    #[Route('/api/payment/confirm', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $paymentIntentId = $data['paymentIntentId'] ?? null;
        $cartId = $data['cartId'] ?? null;
        $userId = $data['userId'] ?? null;

        if (!$paymentIntentId) {
            return new JsonResponse(['error' => 'PaymentIntent manquant.'], 400);
        }

        $intent = $this->stripe->paymentIntents->retrieve($paymentIntentId);
        if ($intent->status !== 'succeeded') {
            return new JsonResponse(['error' => 'Paiement non confirmé.'], 400);
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

        $subtotalTtc = array_reduce(
            $cartItems,
            fn(float $carry, $item) => $carry + ($item->getQuantity() * (float) $item->getUnitPrice()),
            0.0
        );

        $discountTtc = 0.0;
        $totalPaidTtc = $subtotalTtc;
        $totalHt = round($totalPaidTtc / 1.2, 2);

        $order = new Order();
        $order->setUser($user);
        $order->setSubtotalTtc(number_format($subtotalTtc, 2, '.', ''));
        $order->setDiscountTtc(number_format($discountTtc, 2, '.', ''));
        $order->setTotalHt(number_format($totalHt, 2, '.', ''));
        $order->setTotalTtc(number_format($totalPaidTtc, 2, '.', ''));
        $order->setPromoCode(null);
        $order->setStripePromotionCodeId(null);
        $order->setStripeCouponId(null);
        $order->setCurrency($intent->currency ?? 'eur');
        $order->setStatus(Statuses_enums::Payee);
        $order->setDateCommande(new \DateTime());

        $invoiceId = $intent->invoice ?? null;
        if ($invoiceId) {
            $invoice = $this->stripe->invoices->retrieve((string) $invoiceId);
            if ($invoice->subscription) {
                $order->setStripeSubscriptionId((string) $invoice->subscription);
            }
        } elseif (!empty($intent->metadata['sub_id'])) {
            $order->setStripeSubscriptionId($intent->metadata['sub_id']);
        }

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProductPlan()?->getProduct();
            if ($product) {
                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setQuantity($cartItem->getQuantity());
                $orderItem->setPrice($cartItem->getUnitPrice());
                $orderItem->setProductPlan($cartItem->getProductPlan());

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
                    $newQty = max(0, $stock->getQuantite() - $cartItem->getQuantity());
                    $stock->setQuantite($newQty);
                }
            }
        }

        $this->em->persist($order);

        $cartItemsSnapshot = array_map(fn($item) => clone $item, $cartItems);

        foreach ($cartItems as $cartItem) {
            $this->em->remove($cartItem);
        }

        $this->em->flush();

        $this->emailService->sendOrderConfirmationEmail($user, $order, $cartItemsSnapshot);

        return new JsonResponse(['status' => 'created', 'orderId' => $order->getId()]);
    }
}