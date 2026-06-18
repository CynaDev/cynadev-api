<?php

namespace App\MessageHandler;

use App\Entity\ProductPlan;
use App\Message\CreateStripePriceMessage;
use App\Service\StripePriceService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateStripePriceMessageHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private StripePriceService $stripePriceService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateStripePriceMessage $message): void
    {
        $productPlan = $this->em->getRepository(ProductPlan::class)->find($message->getProductPlanId());

        if (!$productPlan instanceof ProductPlan) {
            throw new \RuntimeException(sprintf(
                'ProductPlan introuvable pour l\'id "%d".',
                $message->getProductPlanId()
            ));
        }

        if (null !== $productPlan->getStripePriceId()) {
            $productPlan->setStripeSyncStatus('synced');
            $productPlan->setStripeSyncError(null);
            $this->em->flush();

            return;
        }

        try {
            $stripePriceId = $this->stripePriceService->createRecurringPrice($productPlan);

            $productPlan->setStripePriceId($stripePriceId);
            $productPlan->setStripeSyncStatus('synced');
            $productPlan->setStripeSyncError(null);

            $this->em->flush();

            $this->logger->info('Stripe price created for ProductPlan', [
                'product_plan_id' => $productPlan->getId(),
                'stripe_price_id' => $stripePriceId,
            ]);
        } catch (\Throwable $e) {
            $productPlan->setStripeSyncStatus('failed');
            $productPlan->setStripeSyncError($e->getMessage());
            $this->em->flush();

            $this->logger->error('Stripe price creation failed for ProductPlan', [
                'product_plan_id' => $productPlan->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}