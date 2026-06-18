<?php

namespace App\Service;

use App\Entity\ProductPlan;
use Stripe\StripeClient;

final class StripePriceService
{
    public function __construct(
        private StripeClient $stripeClient,
    ) {
    }

    public function createRecurringPrice(ProductPlan $productPlan): string
    {
        $product = $productPlan->getProduct();

        if (null === $product || null === $product->getStripeProductId()) {
            throw new \RuntimeException('Le produit parent n\'est pas synchronisé avec Stripe.');
        }

        $interval = $this->mapBillingCycle($productPlan->getBillingCycle());

        $stripePrice = $this->stripeClient->prices->create([
            'product' => $product->getStripeProductId(),
            'unit_amount' => (int) round(((float) $productPlan->getPrice()) * 100),
            'currency' => 'eur',
            'recurring' => [
                'interval' => $interval,
            ],
            'nickname' => $productPlan->getName(),
            'metadata' => [
                'local_product_plan_id' => (string) $productPlan->getId(),
                'local_product_id' => (string) $product->getId(),
            ],
        ]);

        return $stripePrice->id;
    }

    public function archivePrice(string $stripePriceId): void
    {
        $this->stripeClient->prices->update($stripePriceId, [
            'active' => false,
        ]);
    }

    public function recreateRecurringPrice(ProductPlan $productPlan, ?string $oldStripePriceId): string
    {
        if ($oldStripePriceId) {
            $this->archivePrice($oldStripePriceId);
        }

        return $this->createRecurringPrice($productPlan);
    }

    private function mapBillingCycle(?string $billingCycle): string
    {
        return match (strtolower((string) $billingCycle)) {
            'month', 'monthly', 'mensuel' => 'month',
            'year', 'yearly', 'annual', 'annuel' => 'year',
            'week', 'weekly', 'hebdo' => 'week',
            'day', 'daily', 'journalier' => 'day',
            default => throw new \InvalidArgumentException(sprintf(
                'billingCycle "%s" non supporté pour Stripe.',
                $billingCycle
            )),
        };
    }
}