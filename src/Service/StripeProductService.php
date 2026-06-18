<?php

namespace App\Service;

use App\Entity\Product;
use Stripe\StripeClient;

final class StripeProductService
{
    public function __construct(
        private StripeClient $stripeClient,
    ) {
    }

    public function createProduct(Product $product): string
    {
        $stripeProduct = $this->stripeClient->products->create([
            'name' => $product->getName(),
            'description' => $product->getDescription() ?: 'Le produit ne possède pas de description.',
            'metadata' => [
                'local_product_id' => (string) $product->getId(),
                'category_id' => (string) $product->getCategoryId(),
            ],
        ]);

        return $stripeProduct->id;
    }

    public function createProductFromData(string $name, ?string $description): string
    {
        $stripeProduct = $this->stripeClient->products->create([
            'name' => $name,
            'description' => $description ?: 'Le produit ne possède pas de description.',
        ]);

        return $stripeProduct->id;
    }

    public function archiveProduct(string $stripeProductId): void
    {
        $this->stripeClient->products->update($stripeProductId, [
            'active' => false,
        ]);
    }
}