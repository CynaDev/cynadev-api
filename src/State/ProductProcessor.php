<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Product;
use App\Message\CreateStripeProductMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final class ProductProcessor implements ProcessorInterface
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
        if (!$data instanceof Product) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        if (null === $data->getDateAjout()) {
            $data->setDateAjout(new \DateTime());
        }

        $data->setStripeSyncStatus('pending');
        $data->setStripeSyncError(null);

        /** @var Product $product */
        $product = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        $this->logger->info('Product persisted after POST', [
            'id' => $product->getId(),
            'name' => $product->getName(),
        ]);

        $this->messageBus->dispatch(new CreateStripeProductMessage(
            $product->getName(),
            $product->getDescription(),
        ));

        $this->logger->info('Dispatch CreateStripeProductMessage', [
            'name' => $product->getName(),
        ]);

        return $product;
    }
}