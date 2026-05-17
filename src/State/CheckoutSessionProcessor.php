<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CheckoutSession as CheckoutSessionResource;
use App\Repository\UserRepository;
use App\Repository\ProductPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;

class CheckoutSessionProcessor implements ProcessorInterface
{
    public function __construct(
        private StripeClient $stripe,
        private UserRepository $userRepository,
        private ProductPlanRepository $productPlanRepository,
        private EntityManagerInterface $em,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CheckoutSessionResource
    {
        $lineItems = array_map(fn($item) => [
            'price'    => $item['stripePriceId'],
            'quantity' => $item['quantity'],
        ], $data->items);

        // Récupérer l'utilisateur en amont
        $user = $data->userId ? $this->userRepository->find((int) $data->userId) : null;

        $params = [
            'payment_method_types' => ['card'],
            'line_items'           => $lineItems,
            'mode'                 => 'subscription',
            'success_url'          => $data->successUrl,
            'cancel_url'           => $data->cancelUrl,
            'metadata'             => [
                'user_id' => $data->userId,
                'cart_id' => $data->cartId,
            ],
        ];

        // Rattacher au customer existant ou utiliser l'email pour en créer un
        if ($user?->getStripeCustomerId()) {
            $params['customer'] = $user->getStripeCustomerId();
        } elseif ($data->customerEmail) {
            $params['customer_email'] = $data->customerEmail;
        }

        $session = $this->stripe->checkout->sessions->create($params);

        // Sauvegarder le stripeCustomerId après la 1ère session
        if ($session->customer && $user && !$user->getStripeCustomerId()) {
            $user->setStripeCustomerId($session->customer);
            $this->em->flush();
        }

        $data->sessionUrl = $session->url;
        return $data;
    }
}