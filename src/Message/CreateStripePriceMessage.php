<?php

namespace App\Message;

final class CreateStripePriceMessage
{
    public function __construct(
        private readonly int $productPlanId,
    ) {
    }

    public function getProductPlanId(): int
    {
        return $this->productPlanId;
    }
}