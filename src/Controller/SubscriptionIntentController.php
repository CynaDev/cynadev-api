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
    ) {
    }

    #[Route('/api/subscription/intent', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->userRepository->find((int) $data['userId']);
        $priceId = $data['priceId'];
        $promoCode = $data['promoCode'] ?? null;

        $customerId = $user->getStripeCustomerId();
        if (!$customerId) {
            $customer = $this->stripe->customers->create([
                'email' => $user->getEmail(),
                'metadata' => ['user_id' => $user->getId()],
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
                $discounts = [['promotion_code' => $promoCodes->data[0]->id]];
            }
        }

        $subscriptionParams = [
            'customer' => $customerId,
            'items' => [['price' => $priceId]],
            'payment_behavior' => 'default_incomplete',
            'collection_method' => 'charge_automatically',
            'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
            'metadata' => [
                'cart_id' => $data['cartId'],
                'user_id' => $data['userId'],
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
                'cart_id' => $data['cartId'],
                'user_id' => $data['userId'],
            ],
            'setup_future_usage' => 'off_session',
        ]);

        return new JsonResponse([
            'clientSecret' => $payIntent->client_secret,
            'subscriptionId' => $subscription->id,
        ]);
    }
}