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
        $data    = json_decode($request->getContent(), true);
        $user    = $this->userRepository->find((int) $data['userId']);
        $priceId = $data['priceId'];

        $customerId = $user->getStripeCustomerId();
        if (!$customerId) {
            $customer   = $this->stripe->customers->create([
                'email'    => $user->getEmail(),
                'metadata' => ['user_id' => $user->getId()],
            ]);
            $customerId = $customer->id;
            $user->setStripeCustomerId($customerId);
            $this->em->flush();
        }

        $subscription = $this->stripe->subscriptions->create([
            'customer'         => $customerId,
            'items'            => [['price' => $priceId]],
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
            'expand'           => ['latest_invoice.payment_intent'],
            'metadata'         => [
                'cart_id' => $data['cartId'],
                'user_id' => $data['userId'],
            ],
        ]);

        $clientSecret = $subscription->latest_invoice->payment_intent->client_secret;

        return new JsonResponse([
            'clientSecret'   => $clientSecret,
            'subscriptionId' => $subscription->id,
        ]);
    }
}