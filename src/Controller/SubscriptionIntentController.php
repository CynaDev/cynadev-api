<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SubscriptionIntentController extends AbstractController
{
    public function __construct(
        private StripeClient $stripe,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/api/subscription/intent', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = $this->userRepository->find((int) ($data['userId'] ?? 0));
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur introuvable.'], 404);
        }

        $promoCode = $data['promoCode'] ?? null;

        $items = $data['items'] ?? [];
        if (empty($items) && isset($data['priceId'])) {
            $items = [[
                'stripePriceId' => $data['priceId'],
                'quantity' => 1,
            ]];
        }

        if (empty($items)) {
            return new JsonResponse(['error' => 'Aucun article fourni.'], 400);
        }

        $customerId = $user->getStripeCustomerId();
        if (!$customerId) {
            $customer = $this->stripe->customers->create([
                'email' => $user->getEmail(),
                'metadata' => [
                    'user_id' => $user->getId(),
                ],
            ]);

            $customerId = $customer->id;
            $user->setStripeCustomerId($customerId);
            $this->em->flush();
        }

        $discounts = [];
        if ($promoCode) {
            $promoCodes = $this->stripe->promotionCodes->all([
                'code' => $promoCode,
                'limit' => 1,
            ]);

            if (!empty($promoCodes->data)) {
                $discounts = [[
                    'promotion_code' => $promoCodes->data[0]->id,
                ]];
            }
        }

        $subscriptionParams = [
            'customer' => $customerId,
            'items' => array_map(
                fn($item) => [
                    'price' => $item['stripePriceId'],
                    'quantity' => $item['quantity'] ?? 1,
                ],
                $items
            ),
            'payment_behavior' => 'default_incomplete',
            'collection_method' => 'charge_automatically',
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
            ],
            'metadata' => [
                'cart_id' => $data['cartId'] ?? '',
                'user_id' => $data['userId'] ?? '',
                'promo_code' => $promoCode ?? '',
            ],
        ];

        if (!empty($discounts)) {
            $subscriptionParams['discounts'] = $discounts;
        }

        $subscription = $this->stripe->subscriptions->create($subscriptionParams);

        $invoiceId = is_string($subscription->latest_invoice)
            ? $subscription->latest_invoice
            : $subscription->latest_invoice->id;

        $invoice = $this->stripe->invoices->retrieve($invoiceId);

        $payIntent = $this->stripe->paymentIntents->create([
            'amount' => $invoice->amount_due,
            'currency' => $invoice->currency,
            'customer' => $customerId,
            'payment_method_types' => ['card'],
            'metadata' => [
                'invoice_id' => $invoiceId,
                'sub_id' => $subscription->id,
                'cart_id' => $data['cartId'] ?? '',
                'user_id' => $data['userId'] ?? '',
                'promo_code' => $promoCode ?? '',
            ],
            'setup_future_usage' => 'off_session',
        ]);

        return new JsonResponse([
            'clientSecret' => $payIntent->client_secret,
            'subscriptionId' => $subscription->id,
        ]);
    }
}