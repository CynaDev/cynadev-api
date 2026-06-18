<?php

namespace App\Service;

use App\Entity\PromoCode;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

final class StripePromoCodeService
{
    public function __construct(
        private StripeClient $stripe,
    ) {
    }

    /**
     * @return array{couponId: string, promotionCodeId: string}
     * @throws ApiErrorException
     */
    public function createPromoCode(PromoCode $promoCode): array
{
    $couponPayload = [
        'duration' => 'forever',
        'name' => $promoCode->getCode(),
        'metadata' => [
            'local_promo_code_id' => (string) $promoCode->getId(),
            'code' => (string) $promoCode->getCode(),
        ],
    ];

    if ('percentage' === $promoCode->getType()) {
        $couponPayload['percent_off'] = $promoCode->getValue();
    } elseif ('fixed' === $promoCode->getType()) {
        $couponPayload['amount_off'] = (int) round($promoCode->getValue() * 100);
        $couponPayload['currency'] = $promoCode->getCurrency();
    } else {
        throw new \RuntimeException('Le type doit être "percentage" ou "fixed".');
    }

    if (null !== $promoCode->getMaxUses() && $promoCode->getMaxUses() > 0) {
        $couponPayload['max_redemptions'] = (int) $promoCode->getMaxUses();
    }

    if (null !== $promoCode->getValidUntil()) {
        $couponPayload['redeem_by'] = (int) $promoCode->getValidUntil()->getTimestamp();
    }

    $coupon = $this->stripe->coupons->create($couponPayload);

    $promotionPayload = [
        'promotion' => [
            'type' => 'coupon',
            'coupon' => $coupon->id,
        ],
        'code' => $promoCode->getCode(),
        'active' => (bool) $promoCode->isIsActive(),
        'metadata' => [
            'local_promo_code_id' => (string) $promoCode->getId(),
        ],
    ];

    if (null !== $promoCode->getMaxUses() && $promoCode->getMaxUses() > 0) {
        $promotionPayload['max_redemptions'] = (int) $promoCode->getMaxUses();
    }

    if (null !== $promoCode->getValidUntil()) {
        $promotionPayload['expires_at'] = (int) $promoCode->getValidUntil()->getTimestamp();
    }

    $promotionCode = $this->stripe->promotionCodes->create($promotionPayload);

    return [
        'couponId' => $coupon->id,
        'promotionCodeId' => $promotionCode->id,
    ];
}

    /**
     * @throws ApiErrorException
     */
    public function updatePromoCode(PromoCode $promoCode): void
    {
        if ($promoCode->getStripePromotionCodeId()) {
            $this->stripe->promotionCodes->update(
                $promoCode->getStripePromotionCodeId(),
                ['active' => $promoCode->isIsActive()]
            );
        }
    }

    /**
     * @throws ApiErrorException
     */
    public function archivePromoCode(PromoCode $promoCode): void
    {
        if ($promoCode->getStripePromotionCodeId()) {
            $this->stripe->promotionCodes->update(
                $promoCode->getStripePromotionCodeId(),
                ['active' => false]
            );
        }
    }
}