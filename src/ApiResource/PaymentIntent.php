<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\PaymentIntentProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/payment-intents',
            processor: PaymentIntentProcessor::class
        )
    ]
)]
class PaymentIntent
{
    public int $amount;
    public string $currency = 'eur';
    public ?string $clientSecret = null;

    // ← AJOUTE
    public ?string $promoCode = null;
    public ?string $customerId = null;
    public ?string $stripePriceId = null;
}