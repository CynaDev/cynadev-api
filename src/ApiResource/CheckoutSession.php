<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\CheckoutSessionProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/checkout-sessions',
            processor: CheckoutSessionProcessor::class
        )
    ]
)]
class CheckoutSession
{
    /** @var array<array{stripePriceId: string, quantity: int}> */
    public array $items = [];

    public string $successUrl = '';
    public string $cancelUrl = '';

    public ?string $sessionUrl = null;

    public ?int $userId = null;
    public ?int $cartId = null;
    public ?string $customerEmail = null;
    public ?string $promoCode = null;
}