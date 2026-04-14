<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CheckoutSession as CheckoutSessionResource;
use Stripe\StripeClient;

class CheckoutSessionProcessor implements ProcessorInterface
{
    public function __construct(private StripeClient $stripe) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CheckoutSessionResource
    {
        $lineItems = array_map(fn($item) => [
            'price_data' => [
                'currency'     => 'eur',
                'unit_amount'  => $item['price'],
                'product_data' => ['name' => $item['name']],
            ],
            'quantity' => $item['quantity'],
        ], $data->items);

        $session = $this->stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items'           => $lineItems,
            'mode'                 => 'payment',
            'success_url'          => $data->successUrl,
            'cancel_url'           => $data->cancelUrl,
        ]);

        $data->sessionUrl = $session->url;
        return $data;
    }
}
