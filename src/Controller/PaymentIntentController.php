<?php

namespace App\Controller;

use App\Repository\CartRepository;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class PaymentIntentController extends AbstractController
{
    public function __construct(private StripeClient $stripe) {}

    #[Route('/api/payment/intent', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $intent = $this->stripe->paymentIntents->create([
            'amount'   => (int) $data['amount'], // centimes
            'currency' => 'eur',
            'automatic_payment_methods' => ['enabled' => true],
            'receipt_email' => $data['customerEmail'] ?? null,
            'metadata' => [
                'cart_id' => $data['cartId'] ?? null,
                'user_id' => $data['userId'] ?? null,
            ],
        ]);

        return new JsonResponse(['clientSecret' => $intent->client_secret]);
    }
}