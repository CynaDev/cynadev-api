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
        // Cas avec subscription + promo (mobile)
        if (!empty($data->stripePriceId) && !empty($data->customerId)) {
            $subscriptionParams = [
                'customer'         => $data->customerId,
                'items'            => [['price' => $data->stripePriceId]],
                'payment_behavior' => 'default_incomplete',
                'expand'           => ['latest_invoice.payment_intent'],
            ];

            // ← Applique le code promo si fourni
            if (!empty($data->promoCode)) {
                $promoCodes = $this->stripe->promotionCodes->all([
                    'code'  => $data->promoCode,
                    'limit' => 1,
                ]);
                if (!empty($promoCodes->data)) {
                    $subscriptionParams['discounts'] = [
                        ['promotion_code' => $promoCodes->data[0]->id]
                    ];
                }
            }

            $subscription = $this->stripe->subscriptions->create($subscriptionParams);
            $data->clientSecret = $subscription->latest_invoice->payment_intent->client_secret;

        } else {
            // Cas simple sans subscription (ancien comportement)
            $intent = $this->stripe->paymentIntents->create([
                'amount'                    => $data->amount,
                'currency'                  => $data->currency,
                'automatic_payment_methods' => ['enabled' => true],
            ]);
            $data->clientSecret = $intent->client_secret;
        }

        return $data;
    }
}