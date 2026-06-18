<?php

namespace App\MessageHandler;

use App\Entity\Product;
use App\Message\CreateStripePriceMessage;
use App\Message\CreateStripeProductMessage;
use App\Service\StripeProductService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler]
final class CreateStripeProductMessageHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private StripeProductService $stripeProductService,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateStripeProductMessage $message): void
    {
        $this->logger->info('Worker received CreateStripeProductMessage', [
            'name' => $message->getName(),
        ]);

        $product = $this->em->getRepository(Product::class)->findOneBy([
            'name' => $message->getName(),
        ]);

        if (!$product instanceof Product) {
            throw new \RuntimeException(sprintf(
                'Produit introuvable pour le nom "%s".',
                $message->getName()
            ));
        }

        if (null !== $product->getStripeProductId()) {
            $product->setStripeSyncStatus('synced');
            $product->setStripeSyncError(null);
            $this->em->flush();

            return;
        }

        $stripeProductId = $this->stripeProductService->createProductFromData(
            $message->getName(),
            $message->getDescription(),
        );

        $product->setStripeProductId($stripeProductId);
        $product->setStripeSyncStatus('synced');
        $product->setStripeSyncError(null);

        $this->em->flush();

        foreach ($product->getProductPlans() as $plan) {
            if (null === $plan->getStripePriceId()) {
                $this->messageBus->dispatch(
                    (new Envelope(new CreateStripePriceMessage($plan->getId())))
                        ->with(new DispatchAfterCurrentBusStamp())
                );
            }
        }

        $this->logger->info('Stripe product created and local product updated', [
            'name' => $message->getName(),
            'stripe_product_id' => $stripeProductId,
            'local_product_id' => $product->getId(),
        ]);
    }
}