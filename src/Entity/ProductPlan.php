<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ProductPlanRepository;
use Doctrine\DBAL\Types\Types;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ProductPlanRepository::class)]
#[ApiFilter(SearchFilter::class, properties: ['product' => 'exact'])]
#[ApiResource(normalizationContext: ['groups' => ['product_plan:read']])]
class ProductPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product_plan:read','cart:items'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'productPlans')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['cart:items'])]
    private ?Product $product = null; // C'est cette propriété qui crée la colonne product_id en DB

    #[ORM\Column(length: 255)]
    #[Groups(['product_plan:read','cart:items'])]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    #[Groups(['product_plan:read','cart:items', 'order:read'])]
    private ?string $billingCycle = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['product_plan:read','cart:items'])]
    private ?string $price = null;

    //Pour subscription
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['product_plan:read','cart:items'])]
    private ?string $stripePriceId = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['product_plan:read','cart:items'])]
    private ?array $features = null;

    public function getStripePriceId(): ?string
    {
        return $this->stripePriceId;
    }
    public function setStripePriceId(?string $id): static
    {
        $this->stripePriceId = $id;
        return $this;
    }

    public function getFeatures(): ?array
    {
        return $this->features;
    }

    public function setFeatures(?array $features): static
    {
        $this->features = $features;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getProduct(): ?Product
    {
        return $this->product;
    }
    public function setProduct(?Product $product): static
    {
        $this->product = $product;
        return $this;
    }
    public function getName(): ?string
    {
        return $this->name;
    }
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }
    public function getBillingCycle(): ?string
    {
        return $this->billingCycle;
    }
    public function setBillingCycle(string $billingCycle): static
    {
        $this->billingCycle = $billingCycle;
        return $this;
    }
    public function getPrice(): ?string
    {
        return $this->price;
    }
    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }
}