<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\PaymentIntent as PaymentIntentResource;
use Stripe\StripeClient;

class PaymentIntentProcessor implements ProcessorInterface
{
    public function __construct(private StripeClient $stripe) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PaymentIntentResource
    {
        $intent = $this->stripe->paymentIntents->create([
            'amount'   => $data->amount,
            'currency' => $data->currency,
            'automatic_payment_methods' => ['enabled' => true],
        ]);

        $data->clientSecret = $intent->client_secret;
        return $data;
    }
}