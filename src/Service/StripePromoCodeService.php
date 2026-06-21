<?php

namespace App\Service;

use App\Entity\PromoCode;
use Stripe\StripeClient;

final class StripePromoCodeService
{
    public function __construct(
        private StripeClient $stripeClient,
    ) {}

    public function createCouponAndPromotionCode(PromoCode $promoCode): array
    {
        $couponPayload = [
            'duration' => 'once',
            'name' => $promoCode->getCode(),
            'metadata' => [
                'local_promo_code_id' => (string) $promoCode->getId(),
                'local_code' => (string) $promoCode->getCode(),
                'type' => (string) $promoCode->getType(),
                'value' => (string) $promoCode->getValue(),
                'currency' => (string) ($promoCode->getCurrency() ?? ''),
            ],
        ];

        if ($promoCode->getType() === 'percentage') {
            $couponPayload['percent_off'] = $promoCode->getValue();
        } elseif ($promoCode->getType() === 'fixed') {
            $couponPayload['amount_off'] = (int) round(((float) $promoCode->getValue()) * 100);
            $couponPayload['currency'] = $promoCode->getCurrency() ?: 'eur';
        } else {
            throw new \InvalidArgumentException(sprintf(
                'Type de promo "%s" non supporté. Attendu: percentage ou fixed.',
                (string) $promoCode->getType()
            ));
        }

        if (null !== $promoCode->getMaxUses()) {
            $couponPayload['max_redemptions'] = $promoCode->getMaxUses();
        }

        if (null !== $promoCode->getValidUntil()) {
            $couponPayload['redeem_by'] = $promoCode->getValidUntil()->getTimestamp();
        }

        $coupon = $this->stripeClient->coupons->create($couponPayload);

        $promotionPayload = [
            'promotion' => [
                'type' => 'coupon',
                'coupon' => $coupon->id,
            ],
            'code' => strtoupper((string) $promoCode->getCode()),
            'active' => (bool) $promoCode->isIsActive(),
            'metadata' => [
                'local_promo_code_id' => (string) $promoCode->getId(),
            ],
        ];

        if (null !== $promoCode->getMaxUses()) {
            $promotionPayload['max_redemptions'] = $promoCode->getMaxUses();
        }

        if (null !== $promoCode->getValidUntil()) {
            $promotionPayload['expires_at'] = $promoCode->getValidUntil()->getTimestamp();
        }

        $promotionCode = $this->stripeClient->promotionCodes->create($promotionPayload);

        return [
            'couponId' => $coupon->id,
            'promotionCodeId' => $promotionCode->id,
        ];
    }

    public function syncUpdate(PromoCode $promoCode): array
    {
        if (!$promoCode->getStripeCouponId() || !$promoCode->getStripePromotionCodeId()) {
            return $this->createCouponAndPromotionCode($promoCode);
        }

        $this->stripeClient->coupons->update($promoCode->getStripeCouponId(), [
            'name' => $promoCode->getCode(),
            'metadata' => [
                'local_promo_code_id' => (string) $promoCode->getId(),
                'local_code' => (string) $promoCode->getCode(),
                'type' => (string) $promoCode->getType(),
                'value' => (string) $promoCode->getValue(),
                'currency' => (string) ($promoCode->getCurrency() ?? ''),
            ],
        ]);

        $promotionUpdatePayload = [
            'active' => (bool) $promoCode->isIsActive(),
            'metadata' => [
                'local_promo_code_id' => (string) $promoCode->getId(),
            ],
        ];

        $this->stripeClient->promotionCodes->update(
            $promoCode->getStripePromotionCodeId(),
            $promotionUpdatePayload
        );

        return [
            'couponId' => $promoCode->getStripeCouponId(),
            'promotionCodeId' => $promoCode->getStripePromotionCodeId(),
        ];
    }
}