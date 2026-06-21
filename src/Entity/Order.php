<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Config\Statuses_enums;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ApiResource(
    operations: [
        new Get(),
        new Post(),
        new GetCollection(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['order:read']],
)]
#[ApiFilter(DateFilter::class, properties: ['dateCommande'])]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'orders', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order:read', 'order:write'])]
    #[ApiFilter(SearchFilter::class, properties: ['user.id'])]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist', 'remove'])]
    #[Groups(['order:read'])]
    private Collection $orderItems;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $subtotalTtc = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['order:read', 'order:write'])]
    private ?string $discountTtc = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $totalHt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $totalTtc = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['order:read'])]
    private ?string $promoCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['order:read'])]
    private ?string $stripePromotionCodeId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['order:read'])]
    private ?string $stripeCouponId = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['order:read'])]
    private ?string $currency = null;

    #[ORM\Column(enumType: Statuses_enums::class)]
    #[Groups(['order:read', 'order:write'])]
    private ?Statuses_enums $status = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['order:read'])]
    private ?\DateTime $dateCommande = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['order:read'])]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['order:read'])]
    private bool $cancelAtPeriodEnd = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['order:read'])]
    private ?\DateTime $subscriptionEndsAt = null;

    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getSubtotalTtc(): ?string
    {
        return $this->subtotalTtc;
    }

    public function setSubtotalTtc(string $subtotalTtc): self
    {
        $this->subtotalTtc = $subtotalTtc;
        return $this;
    }

    public function getDiscountTtc(): ?string
    {
        return $this->discountTtc;
    }

    public function setDiscountTtc(string $discountTtc): self
    {
        $this->discountTtc = $discountTtc;
        return $this;
    }

    public function getTotalHt(): ?string
    {
        return $this->totalHt;
    }

    public function setTotalHt(string $totalHt): self
    {
        $this->totalHt = $totalHt;
        return $this;
    }

    public function getTotalTtc(): ?string
    {
        return $this->totalTtc;
    }

    public function setTotalTtc(string $totalTtc): self
    {
        $this->totalTtc = $totalTtc;
        return $this;
    }

    public function getPromoCode(): ?string
    {
        return $this->promoCode;
    }

    public function setPromoCode(?string $promoCode): self
    {
        $this->promoCode = $promoCode;
        return $this;
    }

    public function getStripePromotionCodeId(): ?string
    {
        return $this->stripePromotionCodeId;
    }

    public function setStripePromotionCodeId(?string $stripePromotionCodeId): self
    {
        $this->stripePromotionCodeId = $stripePromotionCodeId;
        return $this;
    }

    public function getStripeCouponId(): ?string
    {
        return $this->stripeCouponId;
    }

    public function setStripeCouponId(?string $stripeCouponId): self
    {
        $this->stripeCouponId = $stripeCouponId;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getStatus(): ?Statuses_enums
    {
        return $this->status;
    }

    public function setStatus(Statuses_enums $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getDateCommande(): ?\DateTime
    {
        return $this->dateCommande;
    }

    public function setDateCommande(?\DateTime $dateCommande): self
    {
        $this->dateCommande = $dateCommande;
        return $this;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function setStripeSubscriptionId(?string $id): self
    {
        $this->stripeSubscriptionId = $id;
        return $this;
    }

    public function isCancelAtPeriodEnd(): bool
    {
        return $this->cancelAtPeriodEnd;
    }

    public function setCancelAtPeriodEnd(bool $v): self
    {
        $this->cancelAtPeriodEnd = $v;
        return $this;
    }

    public function getSubscriptionEndsAt(): ?\DateTime
    {
        return $this->subscriptionEndsAt;
    }

    public function setSubscriptionEndsAt(?\DateTime $d): self
    {
        $this->subscriptionEndsAt = $d;
        return $this;
    }

    /** @return Collection<int, OrderItem> */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): self
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            $orderItem->setOrder($this);
        }

        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): self
    {
        if ($this->orderItems->removeElement($orderItem)) {
            if ($orderItem->getOrder() === $this) {
                $orderItem->setOrder(null);
            }
        }

        return $this;
    }
}