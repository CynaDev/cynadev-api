<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\State\PromoCodeProcessor;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(processor: PromoCodeProcessor::class),
        new Patch(processor: PromoCodeProcessor::class),
        new Put(processor: PromoCodeProcessor::class),
        new Delete(),
    ]
)]
class PromoCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    // decimal Doctrine => string côté PHP/API pour éviter les erreurs de précision
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $value = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxUses = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $validUntil = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeCouponId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePromotionCodeId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $stripeSyncStatus = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $stripeSyncError = null;

    public function getId(): ?int { return $this->id; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getValue(): ?string { return $this->value; }
    public function setValue(string $value): static { $this->value = $value; return $this; }
    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $currency): static { $this->currency = $currency; return $this; }
    public function getMaxUses(): ?int { return $this->maxUses; }
    public function setMaxUses(?int $maxUses): static { $this->maxUses = $maxUses; return $this; }
    public function getValidUntil(): ?\DateTimeInterface { return $this->validUntil; }
    public function setValidUntil(?\DateTimeInterface $validUntil): static { $this->validUntil = $validUntil; return $this; }
    public function isIsActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getStripeCouponId(): ?string { return $this->stripeCouponId; }
    public function setStripeCouponId(?string $id): static { $this->stripeCouponId = $id; return $this; }
    public function getStripePromotionCodeId(): ?string { return $this->stripePromotionCodeId; }
    public function setStripePromotionCodeId(?string $id): static { $this->stripePromotionCodeId = $id; return $this; }
    public function getStripeSyncStatus(): ?string { return $this->stripeSyncStatus; }
    public function setStripeSyncStatus(?string $status): static { $this->stripeSyncStatus = $status; return $this; }
    public function getStripeSyncError(): ?string { return $this->stripeSyncError; }
    public function setStripeSyncError(?string $error): static { $this->stripeSyncError = $error; return $this; }
}