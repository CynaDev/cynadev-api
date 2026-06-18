<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ProductPlan;
use App\Service\StripePriceService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ProductPlanDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private StripePriceService $stripePriceService,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ProductPlan) {
            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        if (null !== $data->getStripePriceId()) {
            try {
                $this->stripePriceService->archivePrice($data->getStripePriceId());

                $this->logger->info('Stripe price archived before ProductPlan delete', [
                    'product_plan_id' => $data->getId(),
                    'stripe_price_id' => $data->getStripePriceId(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to archive Stripe price before ProductPlan delete', [
                    'product_plan_id' => $data->getId(),
                    'stripe_price_id' => $data->getStripePriceId(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}