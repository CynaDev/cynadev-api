<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CheckoutSession as CheckoutSessionResource;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\StripeClient;

class CheckoutSessionProcessor implements ProcessorInterface
{
    public function __construct(
        private StripeClient $stripe,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CheckoutSessionResource
    {
        $lineItems = array_map(
            fn(array $item) => [
                'price' => $item['stripePriceId'],
                'quantity' => $item['quantity'],
            ],
            $data->items
        );

        $user = $data->userId ? $this->userRepository->find((int) $data->userId) : null;

        $promoCode = $data->promoCode !== null ? trim($data->promoCode) : null;
        if ($promoCode === '') {
            $promoCode = null;
        }

        $params = [
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'subscription',
            'success_url' => $data->successUrl,
            'cancel_url' => $data->cancelUrl,
            'locale' => 'fr',
            'metadata' => [
                'user_id' => (string) ($data->userId ?? ''),
                'cart_id' => (string) ($data->cartId ?? ''),
                'promo_code_input' => $promoCode ?? '',
            ],
        ];

        if ($user?->getStripeCustomerId()) {
            $params['customer'] = $user->getStripeCustomerId();
        } elseif ($data->customerEmail) {
            $params['customer_email'] = $data->customerEmail;
        }

        if ($promoCode !== null) {
            $promoCodes = $this->stripe->promotionCodes->all([
                'code' => $promoCode,
                'active' => true,
                'limit' => 1,
            ]);

            if (!empty($promoCodes->data)) {
                $params['discounts'] = [
                    ['promotion_code' => $promoCodes->data[0]->id],
                ];
            } else {
                $params['allow_promotion_codes'] = true;

                $this->logger->warning('Promotion code Stripe introuvable', [
                    'promo_code' => $promoCode,
                    'user_id' => $data->userId,
                    'cart_id' => $data->cartId,
                ]);
            }
        } else {
            $params['allow_promotion_codes'] = true;
        }

        $session = $this->stripe->checkout->sessions->create($params);

        if ($session->customer && $user && !$user->getStripeCustomerId()) {
            $user->setStripeCustomerId($session->customer);
            $this->em->flush();
        }

        $data->sessionUrl = $session->url;

        return $data;
    }
}