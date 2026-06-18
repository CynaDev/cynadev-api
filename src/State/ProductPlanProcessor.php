<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ProductPlan;
use App\Message\CreateStripePriceMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final class ProductPlanProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private MessageBusInterface $messageBus,
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
            throw new \RuntimeException('Le produit doit être synchronisé avec Stripe avant de créer un plan.');
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

        $this->messageBus->dispatch(new CreateStripePriceMessage(
            $productPlan->getId(),
        ));

        return $productPlan;
    }
}