<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ProductPlan;
use App\Service\StripePriceService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ProductPlanProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private StripePriceService $stripePriceService,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ProductPlan) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $product = $data->getProduct();

        if (null === $product) {
            throw new \RuntimeException('Un plan doit être lié à un produit.');
        }

        $data->setStripeSyncStatus('pending');
        $data->setStripeSyncError(null);

        /** @var ProductPlan $productPlan */
        $productPlan = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        $this->logger->info('ProductPlan persisted after POST', [
            'id' => $productPlan->getId(),
            'name' => $productPlan->getName(),
            'product_id' => $product->getId(),
            'stripe_product_id' => $product->getStripeProductId(),
        ]);

        if (null === $product->getStripeProductId()) {
            $this->logger->info('ProductPlan created but Stripe product is not ready yet', [
                'product_plan_id' => $productPlan->getId(),
                'product_id' => $product->getId(),
            ]);

            return $productPlan;
        }

        try {
            if (null === $productPlan->getStripePriceId()) {
                $stripePriceId = $this->stripePriceService->createRecurringPrice($productPlan);

                $productPlan->setStripePriceId($stripePriceId);
            }

            $productPlan->setStripeSyncStatus('synced');
            $productPlan->setStripeSyncError(null);

            $this->logger->info('Stripe price created after ProductPlan POST', [
                'product_plan_id' => $productPlan->getId(),
                'stripe_price_id' => $productPlan->getStripePriceId(),
            ]);

            return $this->persistProcessor->process($productPlan, $operation, $uriVariables, $context);
        } catch (\Throwable $e) {
            $productPlan->setStripeSyncStatus('failed');
            $productPlan->setStripeSyncError($e->getMessage());

            $this->persistProcessor->process($productPlan, $operation, $uriVariables, $context);

            $this->logger->error('Stripe sync failed during ProductPlan POST', [
                'product_plan_id' => $productPlan->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}