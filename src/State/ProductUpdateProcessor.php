<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Product;
use App\Service\StripeProductService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ProductUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private StripeProductService $stripeProductService,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Product) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        /** @var Product|null $previousData */
        $previousData = $context['previous_data'] ?? null;

        $oldName = $previousData?->getName();
        $newName = $data->getName();

        $nameChanged = $previousData instanceof Product
            && $previousData->getName() !== $data->getName();

        $descriptionChanged = $previousData instanceof Product
            && $previousData->getDescription() !== $data->getDescription();

        $mustSyncStripe = $nameChanged || $descriptionChanged;

        $data->setStripeSyncStatus('pending');
        $data->setStripeSyncError(null);

        /** @var Product $product */
        $product = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if (!$mustSyncStripe) {
            $product->setStripeSyncStatus('synced');
            $product->setStripeSyncError(null);

            $this->logger->info('Product updated locally without Stripe sync', [
                'product_id' => $product->getId(),
                'old_name' => $oldName,
                'new_name' => $newName,
            ]);

            return $this->persistProcessor->process($product, $operation, $uriVariables, $context);
        }

        if (null === $oldName) {
            $product->setStripeSyncStatus('failed');
            $product->setStripeSyncError('Impossible de retrouver le nom précédent du produit pour synchroniser Stripe.');

            $this->persistProcessor->process($product, $operation, $uriVariables, $context);

            throw new \RuntimeException('Impossible de retrouver le nom précédent du produit pour synchroniser Stripe.');
        }

        try {
            $stripeProductId = $this->stripeProductService->updateProductByName(
                $oldName,
                $product->getName(),
                $product->getDescription(),
            );

            $product->setStripeProductId($stripeProductId);
            $product->setStripeSyncStatus('synced');
            $product->setStripeSyncError(null);

            $this->logger->info('Stripe product updated after Product PATCH using product name', [
                'product_id' => $product->getId(),
                'old_name' => $oldName,
                'new_name' => $product->getName(),
                'stripe_product_id' => $stripeProductId,
                'name_changed' => $nameChanged,
                'description_changed' => $descriptionChanged,
            ]);

            return $this->persistProcessor->process($product, $operation, $uriVariables, $context);
        } catch (\Throwable $e) {
            $product->setStripeSyncStatus('failed');
            $product->setStripeSyncError($e->getMessage());

            $this->persistProcessor->process($product, $operation, $uriVariables, $context);

            $this->logger->error('Stripe sync failed during Product update using product name', [
                'product_id' => $product->getId(),
                'old_name' => $oldName,
                'new_name' => $product->getName(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}