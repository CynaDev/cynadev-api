<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ProductPlanRepository;
use App\State\ProductPlanDeleteProcessor;
use App\State\ProductPlanProcessor;
use App\State\ProductPlanUpdateProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ProductPlanRepository::class)]
#[ApiFilter(SearchFilter::class, properties: ['product' => 'exact'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(processor: ProductPlanProcessor::class),
        new Patch(processor: ProductPlanUpdateProcessor::class),
        new Delete(processor: ProductPlanDeleteProcessor::class),
    ],
    normalizationContext: ['groups' => ['product_plan:read']]
)]
class ProductPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product_plan:read', 'cart:items'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'productPlans')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['cart:items'])]
    private ?Product $product = null;

    #[ORM\Column(length: 255)]
    #[Groups(['product_plan:read', 'cart:items'])]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    #[Groups(['product_plan:read', 'cart:items', 'order:read'])]
    private ?string $billingCycle = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['product_plan:read', 'cart:items'])]
    private ?string $price = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['product_plan:read', 'cart:items'])]
    private ?string $stripePriceId = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['product_plan:read', 'cart:items'])]
    private ?array $features = null;

    #[ORM\Column(length: 50, options: ['default' => 'pending'])]
    #[Groups(['product_plan:read'])]
    private ?string $stripeSyncStatus = 'pending';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['product_plan:read'])]
    private ?string $stripeSyncError = null;

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

    public function getStripeSyncStatus(): ?string
    {
        return $this->stripeSyncStatus;
    }

    public function setStripeSyncStatus(?string $stripeSyncStatus): static
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