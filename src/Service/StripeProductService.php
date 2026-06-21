<?php

namespace App\Service;

use App\Entity\Product;
use Stripe\StripeClient;

final class StripeProductService
{
    public function __construct(
        private StripeClient $stripeClient,
    ) {}

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

    public function updateProductByName(string $oldName, ?string $newName, ?string $description): string
    {
        $results = $this->stripeClient->products->search([
            'query' => sprintf('name:\'%s\'', str_replace('\'', '\\\'', $oldName)),
            'limit' => 1,
        ]);

        $stripeProduct = $results->data[0] ?? null;

        if (null === $stripeProduct) {
            throw new \RuntimeException(sprintf(
                'Aucun produit Stripe trouvé pour le nom "%s".',
                $oldName
            ));
        }

        $updated = $this->stripeClient->products->update($stripeProduct->id, array_filter([
            'name' => $newName,
            'description' => $description,
        ], static fn ($value) => null !== $value));

        return $updated->id;
    }
}