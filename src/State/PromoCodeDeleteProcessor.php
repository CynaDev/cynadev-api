<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\PromoCode;
use App\Service\StripePromoCodeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PromoCodeDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private StripePromoCodeService $stripePromoCodeService,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof PromoCode) {
            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        try {
            $this->stripePromoCodeService->archivePromoCode($data);

            $this->logger->info('PromoCode archived on Stripe before local delete', [
                'promo_code_id' => $data->getId(),
                'code' => $data->getCode(),
                'stripe_promotion_code_id' => $data->getStripePromotionCodeId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('PromoCode Stripe archive failed before delete', [
                'promo_code_id' => $data->getId(),
                'code' => $data->getCode(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}