<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\PromoCode;
use App\Service\StripePromoCodeService;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PromoCodeProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private StripePromoCodeService $stripePromoCodeService,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof PromoCode) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $this->validate($data);

        $data->setStripeSyncStatus('pending');
        $data->setStripeSyncError(null);

        /** @var PromoCode $promoCode */
        $promoCode = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        try {
            if (null === $promoCode->getStripeCouponId() || null === $promoCode->getStripePromotionCodeId()) {
                $stripeIds = $this->stripePromoCodeService->createPromoCode($promoCode);
                $promoCode
                    ->setStripeCouponId($stripeIds['couponId'])
                    ->setStripePromotionCodeId($stripeIds['promotionCodeId']);
            } else {
                $this->stripePromoCodeService->updatePromoCode($promoCode);
            }

            $promoCode
                ->setStripeSyncStatus('synced')
                ->setStripeSyncError(null);

            $this->persistProcessor->process($promoCode, $operation, $uriVariables, $context);

            $this->logger->info('PromoCode synced with Stripe', [
                'promo_code_id' => $promoCode->getId(),
                'code' => $promoCode->getCode(),
                'stripe_coupon_id' => $promoCode->getStripeCouponId(),
                'stripe_promotion_code_id' => $promoCode->getStripePromotionCodeId(),
            ]);
        } catch (ApiErrorException|\Throwable $e) {
            $promoCode
                ->setStripeSyncStatus('failed')
                ->setStripeSyncError($e->getMessage());

            $this->persistProcessor->process($promoCode, $operation, $uriVariables, $context);

            $this->logger->error('PromoCode Stripe sync failed', [
                'promo_code_id' => $promoCode->getId(),
                'code' => $promoCode->getCode(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $promoCode;
    }

    private function validate(PromoCode $promoCode): void
    {
        if (!in_array($promoCode->getType(), ['percentage', 'fixed'], true)) {
            throw new \RuntimeException('Le type doit être "percentage" ou "fixed".');
        }

        if ('percentage' === $promoCode->getType()) {
            if ($promoCode->getValue() <= 0 || $promoCode->getValue() > 100) {
                throw new \RuntimeException('Une réduction percentage doit être comprise entre 0 et 100.');
            }
        }

        if ('fixed' === $promoCode->getType()) {
            if ($promoCode->getValue() <= 0) {
                throw new \RuntimeException('Une réduction fixed doit être supérieure à 0.');
            }

            if (!$promoCode->getCurrency()) {
                throw new \RuntimeException('La devise est obligatoire pour une réduction fixed.');
            }
        }
    }
}