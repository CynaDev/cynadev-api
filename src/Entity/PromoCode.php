<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\PromoCodeRepository;
use App\State\PromoCodeDeleteProcessor;
use App\State\PromoCodeProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: PromoCodeRepository::class)]
#[ApiResource(
    processor: PromoCodeProcessor::class,
    operations: [
        new \ApiPlatform\Metadata\Get(),
        new \ApiPlatform\Metadata\GetCollection(),
        new \ApiPlatform\Metadata\Post(processor: PromoCodeProcessor::class),
        new \ApiPlatform\Metadata\Patch(processor: PromoCodeProcessor::class),
        new \ApiPlatform\Metadata\Delete(processor: PromoCodeDeleteProcessor::class),
    ]
)]
#[UniqueEntity(fields: ['code'], message: 'Ce code promo existe déjà.')]
#[ApiFilter(SearchFilter::class, properties: ['code' => 'exact'])]
class PromoCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    // percent | fixed
    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column(type: 'float')]
    private ?float $value = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validFrom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validUntil = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxUses = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $currentUses = 0;

    #[ORM\Column(options: ['default' => true])]
    private ?bool $isActive = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeCouponId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePromotionCodeId = null;

    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private ?string $stripeSyncStatus = 'pending';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $stripeSyncError = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = strtolower($type);
        return $this;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(float $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): static
    {
        $this->currency = $currency ? strtolower($currency) : null;
        return $this;
    }

    public function getValidFrom(): ?\DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeInterface $validFrom): static
    {
        $this->validFrom = $validFrom;
        return $this;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeInterface $validUntil): static
    {
        $this->validUntil = $validUntil;
        return $this;
    }

    public function getMaxUses(): ?int
    {
        return $this->maxUses;
    }

    public function setMaxUses(?int $maxUses): static
    {
        $this->maxUses = $maxUses;
        return $this;
    }

    public function getCurrentUses(): ?int
    {
        return $this->currentUses;
    }

    public function setCurrentUses(int $currentUses): static
    {
        $this->currentUses = $currentUses;
        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getStripeCouponId(): ?string
    {
        return $this->stripeCouponId;
    }

    public function setStripeCouponId(?string $stripeCouponId): static
    {
        $this->stripeCouponId = $stripeCouponId;
        return $this;
    }

    public function getStripePromotionCodeId(): ?string
    {
        return $this->stripePromotionCodeId;
    }

    public function setStripePromotionCodeId(?string $stripePromotionCodeId): static
    {
        $this->stripePromotionCodeId = $stripePromotionCodeId;
        return $this;
    }

    public function getStripeSyncStatus(): ?string
    {
        return $this->stripeSyncStatus;
    }

    public function setStripeSyncStatus(string $stripeSyncStatus): static
    {
        $this->stripeSyncStatus = $stripeSyncStatus;
        return $this;
    }

    public function getStripeSyncError(): ?string
    {
        return $this->stripeSyncError;
    }

    public function setStripeSyncError(?string $stripeSyncError): static
    {
        $this->stripeSyncError = $stripeSyncError;
        return $this;
    }
}