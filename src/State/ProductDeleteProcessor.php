<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Product;
use App\Service\StripeProductService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ProductDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private StripeProductService $stripeProductService,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
{
    if (!$data instanceof Product) {
        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }

    if ($data->getStripeProductId()) {
        $this->logger->info('Archiving Stripe product before local delete', [
            'local_product_id' => $data->getId(),
            'stripe_product_id' => $data->getStripeProductId(),
        ]);

        try {
            $this->stripeProductService->archiveProduct($data->getStripeProductId());
        } catch (\Throwable $e) {
            $this->logger->error('Failed to archive Stripe product before local delete', [
                'local_product_id' => $data->getId(),
                'stripe_product_id' => $data->getStripeProductId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
}
}