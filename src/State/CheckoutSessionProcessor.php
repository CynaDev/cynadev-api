<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CheckoutSession as CheckoutSessionResource;
use App\Repository\PromoCodeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;

class CheckoutSessionProcessor implements ProcessorInterface
{
    public function __construct(
        private StripeClient $stripe,
        private UserRepository $userRepository,
        private PromoCodeRepository $promoCodeRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CheckoutSessionResource
    {
        $lineItems = array_map(fn($item) => [
            'price' => $item['stripePriceId'],
            'quantity' => $item['quantity'],
        ], $data->items);

        $user = $data->userId ? $this->userRepository->find((int) $data->userId) : null;

        $params = [
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'subscription',
            'success_url' => $data->successUrl,
            'cancel_url' => $data->cancelUrl,
            'metadata' => [
                'user_id' => (string) ($data->userId ?? ''),
                'cart_id' => (string) ($data->cartId ?? ''),
                'promo_code' => (string) ($data->promoCode ?? ''),
            ],
        ];

        if (!empty($data->promoCode)) {
            $promo = $this->promoCodeRepository->findOneBy([
                'code' => strtoupper(trim($data->promoCode)),
            ]);

            if (
                $promo
                && $promo->isIsActive()
                && $promo->getStripePromotionCodeId()
                && (!$promo->getValidFrom() || $promo->getValidFrom() <= new \DateTime())
                && (!$promo->getValidUntil() || $promo->getValidUntil() >= new \DateTime())
            ) {
                $params['discounts'] = [
                    [
                        'promotion_code' => $promo->getStripePromotionCodeId(),
                    ],
                ];
            }
        }

        if ($user?->getStripeCustomerId()) {
            $params['customer'] = $user->getStripeCustomerId();
        } elseif ($data->customerEmail) {
            $params['customer_email'] = $data->customerEmail;
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