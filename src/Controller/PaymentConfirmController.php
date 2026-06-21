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

        $intent = $this->stripe->paymentIntents->retrieve($paymentIntentId, [
            'expand' => ['invoice', 'invoice.subscription'],
        ]);

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
        $promoCode = null;
        $stripePromotionCodeId = null;
        $stripeCouponId = null;
        $stripeSubscriptionId = null;

        $invoiceObject = $intent->invoice ?? null;

        if ($invoiceObject) {
            $invoiceId = is_string($invoiceObject) ? $invoiceObject : $invoiceObject->id;

            $invoice = $this->stripe->invoices->retrieve((string) $invoiceId, [
                'expand' => ['total_details.breakdown.discounts', 'subscription'],
            ]);

            $totalPaidTtc = ($invoice->amount_paid ?? 0) / 100;

            $rawDiscount = 0;
            if (!empty($invoice->total_discount_amounts) && isset($invoice->total_discount_amounts[0]->amount)) {
                $rawDiscount = $invoice->total_discount_amounts[0]->amount;
            } elseif (isset($invoice->total_details->amount_discount)) {
                $rawDiscount = $invoice->total_details->amount_discount;
            }
            $discountTtc = $rawDiscount / 100;

            $discounts = $invoice->total_details->breakdown->discounts ?? [];
            if (!empty($discounts)) {
                $firstDiscount = $discounts[0]->discount ?? null;

                if ($firstDiscount) {
                    $promotionCodeObject = $firstDiscount->promotion_code ?? null;
                    if (is_object($promotionCodeObject)) {
                        $stripePromotionCodeId = $promotionCodeObject->id ?? null;
                        $promoCode = $promotionCodeObject->code ?? null;
                    } elseif (is_string($promotionCodeObject)) {
                        $stripePromotionCodeId = $promotionCodeObject;
                    }

                    $couponObject = $firstDiscount->coupon ?? null;
                    if (is_object($couponObject)) {
                        $stripeCouponId = $couponObject->id ?? null;
                    } elseif (is_string($couponObject)) {
                        $stripeCouponId = $couponObject;
                    }
                }
            }

            if ($invoice->subscription) {
                $stripeSubscriptionId = is_string($invoice->subscription)
                    ? $invoice->subscription
                    : $invoice->subscription->id;
            }
        } else {
            $totalPaidTtc = ($intent->amount_received ?? 0) / 100;

            if (!empty($intent->metadata['sub_id'])) {
                $stripeSubscriptionId = $intent->metadata['sub_id'];
            }
        }

        if (!$promoCode && !empty($intent->metadata['promo_code'])) {
            $promoCode = $intent->metadata['promo_code'];
        }

        if (
            !$promoCode &&
            $stripeSubscriptionId
        ) {
            $subscription = $this->stripe->subscriptions->retrieve($stripeSubscriptionId);

            if (!empty($subscription->metadata['promo_code'])) {
                $promoCode = $subscription->metadata['promo_code'];
            }
        }

        $totalHt = round($totalPaidTtc / 1.2, 2);

        $order = new Order();
        $order->setUser($user);
        $order->setSubtotalTtc(number_format($subtotalTtc, 2, '.', ''));
        $order->setDiscountTtc(number_format($discountTtc, 2, '.', ''));
        $order->setTotalHt(number_format($totalHt, 2, '.', ''));
        $order->setTotalTtc(number_format($totalPaidTtc, 2, '.', ''));
        $order->setPromoCode($promoCode);
        $order->setStripePromotionCodeId($stripePromotionCodeId);
        $order->setStripeCouponId($stripeCouponId);
        $order->setCurrency($intent->currency ?? 'eur');
        $order->setStatus(Statuses_enums::Payee);
        $order->setDateCommande(new \DateTime());

        if ($stripeSubscriptionId) {
            $order->setStripeSubscriptionId($stripeSubscriptionId);
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

        return new JsonResponse([
            'status' => 'created',
            'orderId' => $order->getId(),
        ]);
    }
}