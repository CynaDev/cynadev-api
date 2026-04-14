<?php

namespace App\Controller;

use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController
{
    public function __construct(
        private string $webhookSecret,
    ) {}

    #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
        } catch (SignatureVerificationException) {
            return new Response('Invalid signature', 400);
        }

        match ($event->type) {
            'payment_intent.succeeded'         => $this->handleSuccess($event->data->object),
            'payment_intent.payment_failed'    => $this->handleFailure($event->data->object),
            default                            => null,
        };

        return new Response('OK');
    }

    private function handleSuccess(object $intent): void
    {
        // Mettez à jour votre Order en base de données
    }

    private function handleFailure(object $intent): void
    {
        // Logguez l'échec, notifiez l'utilisateur
    }
}