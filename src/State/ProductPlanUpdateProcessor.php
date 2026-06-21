<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ProductPlan;
use App\Service\StripePriceService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ProductPlanUpdateProcessor implements ProcessorInterface
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

        if (null === $product->getStripeProductId()) {
            throw new \RuntimeException('Le produit doit être synchronisé avec Stripe avant de modifier un plan.');
        }

        /** @var ProductPlan|null $previousData */
        $previousData = $context['previous_data'] ?? null;

        $mustCreatePrice = null === $data->getStripePriceId();

        $mustRecreatePrice =
            $previousData instanceof ProductPlan
            && null !== $previousData->getStripePriceId()
            && (
                $previousData->getPrice() !== $data->getPrice()
                || $previousData->getBillingCycle() !== $data->getBillingCycle()
                || $previousData->getProduct()?->getId() !== $data->getProduct()?->getId()
            );

        $data->setStripeSyncStatus('pending');
        $data->setStripeSyncError(null);

        /** @var ProductPlan $productPlan */
        $productPlan = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        try {
            if ($mustCreatePrice) {
                $newStripePriceId = $this->stripePriceService->createRecurringPrice($productPlan);

                $productPlan->setStripePriceId($newStripePriceId);

                $this->logger->info('Stripe price created after ProductPlan update', [
                    'product_plan_id' => $productPlan->getId(),
                    'stripe_price_id' => $newStripePriceId,
                ]);
            } elseif ($mustRecreatePrice) {
                $newStripePriceId = $this->stripePriceService->recreateRecurringPrice(
                    $productPlan,
                    $previousData?->getStripePriceId(),
                );

                $productPlan->setStripePriceId($newStripePriceId);

                $this->logger->info('Stripe price recreated after ProductPlan update', [
                    'product_plan_id' => $productPlan->getId(),
                    'old_stripe_price_id' => $previousData?->getStripePriceId(),
                    'new_stripe_price_id' => $newStripePriceId,
                ]);
            }

            $productPlan->setStripeSyncStatus('synced');
            $productPlan->setStripeSyncError(null);

            return $this->persistProcessor->process($productPlan, $operation, $uriVariables, $context);
        } catch (\Throwable $e) {
            $productPlan->setStripeSyncStatus('failed');
            $productPlan->setStripeSyncError($e->getMessage());

            $this->persistProcessor->process($productPlan, $operation, $uriVariables, $context);

            $this->logger->error('Stripe sync failed during ProductPlan update', [
                'product_plan_id' => $productPlan->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}