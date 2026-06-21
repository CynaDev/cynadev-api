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
        $data           = json_decode($request->getContent(), true);
        $paymentIntentId = $data['paymentIntentId'] ?? null;
        $cartId         = $data['cartId'] ?? null;
        $userId         = $data['userId'] ?? null;

        if (!$paymentIntentId) {
            return new JsonResponse(['error' => 'PaymentIntent manquant.'], 400);
        }

        // ── Récupère le PaymentIntent avec l'invoice expandée ──────────────
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

        // ── Calcul des montants depuis Stripe (réduction incluse) ───────────
        $subtotalTtc           = array_reduce(
            $cartItems,
            fn(float $carry, $item) => $carry + ($item->getQuantity() * (float) $item->getUnitPrice()),
            0.0
        );
        $discountTtc           = 0.0;
        $totalPaidTtc          = $subtotalTtc;
        $promoCode             = null;
        $stripePromotionCodeId = null;
        $stripeCouponId        = null;
        $stripeSubscriptionId  = null;

        $invoiceObject = $intent->invoice ?? null;

        if ($invoiceObject) {
            // Ré-expand l'invoice pour avoir total_details.breakdown.discounts
            $invoiceId = is_string($invoiceObject) ? $invoiceObject : $invoiceObject->id;
            $invoice   = $this->stripe->invoices->retrieve((string) $invoiceId, [
                'expand' => ['total_details.breakdown.discounts'],
            ]);

            $totalPaidTtc = $invoice->amount_paid / 100;

            $rawDiscount = $invoice->total_discount_amounts[0]->amount ?? 0;
            $discountTtc = $rawDiscount / 100;


            $discounts = $invoice->total_details->breakdown->discounts ?? [];
            if (!empty($discounts)) {
                $firstDiscount = $discounts[0]->discount ?? null;
                if ($firstDiscount) {
                    $promoCodeObj = $firstDiscount->promotion_code ?? null;
                    if (is_object($promoCodeObj)) {
                        $stripePromotionCodeId = $promoCodeObj->id   ?? null;
                        $promoCode             = $promoCodeObj->code ?? null;
                    } elseif (is_string($promoCodeObj)) {
                        $stripePromotionCodeId = $promoCodeObj;
                    }

                    $couponObj = $firstDiscount->coupon ?? null;
                    if (is_object($couponObj)) {
                        $stripeCouponId = $couponObj->id ?? null;
                    } elseif (is_string($couponObj)) {
                        $stripeCouponId = $couponObj;
                    }
                }
            }

            if ($invoice->subscription) {
                $stripeSubscriptionId = is_string($invoice->subscription)
                    ? $invoice->subscription
                    : $invoice->subscription->id;
            }
        } else {
            // Pas d'invoice → paiement one-shot, montant depuis amount_received
            $totalPaidTtc = $intent->amount_received / 100;

            // Récupère le sub_id depuis les metadata si présent
            if (!empty($intent->metadata['sub_id'])) {
                $stripeSubscriptionId = $intent->metadata['sub_id'];
            }
        }

        $totalHt = round($totalPaidTtc / 1.2, 2);

        // ── Création de la commande ─────────────────────────────────────────
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

        // ── Order items + snapshots ─────────────────────────────────────────
        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProductPlan()?->getProduct();
            if (!$product) continue;

            $orderItem = new OrderItem();
            $orderItem->setProduct($product);
            $orderItem->setQuantity($cartItem->getQuantity());
            $orderItem->setPrice($cartItem->getUnitPrice());
            $orderItem->setProductPlan($cartItem->getProductPlan());
            $orderItem->setProductName($product->getName());

            $orderItem->setProductSnapshot([
                'id'            => $product->getId(),
                'name'          => $product->getName(),
                'description'   => $product->getDescription(),
                'price'         => $product->getPrice(),
                'categoryId'    => $product->getCategoryId(),
                'disponibilite' => $product->getDisponibilite()?->name,
            ]);

            $productPlan = $cartItem->getProductPlan();
            if ($productPlan) {
                $orderItem->setProductPlanSnapshot([
                    'id'           => $productPlan->getId(),
                    'name'         => $productPlan->getName(),
                    'billingCycle' => $productPlan->getBillingCycle(),
                    'price'        => $productPlan->getPrice(),
                    'stripePriceId'=> $productPlan->getStripePriceId(),
                    'features'     => $productPlan->getFeatures(),
                ]);
            }

            $order->addOrderItem($orderItem);
            $this->em->persist($orderItem);

            $stock = $this->stockRepository->findOneBy(['product' => $product]);
            if ($stock !== null) {
                $stock->setQuantite(max(0, $stock->getQuantite() - $cartItem->getQuantity()));
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