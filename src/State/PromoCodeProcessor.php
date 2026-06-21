<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\PromoCode;
use App\Service\StripePromoCodeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PromoCodeProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private StripePromoCodeService $stripePromoCodeService,
        private LoggerInterface $logger,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof PromoCode) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }
        /** @var PromoCode $promoCode */
        $promoCode = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        try {
            $stripeIds = $this->stripePromoCodeService->syncUpdate($promoCode);

            $promoCode->setStripeCouponId($stripeIds['couponId']);
            $promoCode->setStripePromotionCodeId($stripeIds['promotionCodeId']);
            $promoCode->setStripeSyncStatus('synced');
            $promoCode->setStripeSyncError(null);

            $this->logger->info('Promo code synced to Stripe', [
                'id' => $promoCode->getId(),
                'couponId' => $stripeIds['couponId'],
                'promotionCodeId' => $stripeIds['promotionCodeId'],
            ]);
        } catch (\Throwable $e) {
            $promoCode->setStripeSyncStatus('error');
            $promoCode->setStripeSyncError($e->getMessage());

            $this->logger->error('Promo code Stripe sync failed', [
                'error' => $e->getMessage(),
            ]);
        }

        /** @var PromoCode $promoCode */
        $promoCode = $this->persistProcessor->process($promoCode, $operation, $uriVariables, $context);

        return $promoCode;
    }
}