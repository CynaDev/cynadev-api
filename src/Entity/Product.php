<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Config\Disponibilite_enums;
use App\Repository\ProductRepository;
use App\State\ProductProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use App\State\ProductDeleteProcessor;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(processor: ProductProcessor::class),
        new Patch(),
        new Delete(processor: ProductDeleteProcessor::class),
    ],
    paginationEnabled: true,
    paginationItemsPerPage: 5
)]
#[ApiFilter(SearchFilter::class, properties: ['categoryId' => 'exact', 'name' => 'partial'])]
#[ApiFilter(RangeFilter::class, properties: ['price'])]
#[ApiFilter(OrderFilter::class, properties: ['price', 'dateAjout'])]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cart:items', 'order:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['cart:items', 'order:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['cart:items', 'order:read'])]
    private ?int $categoryId = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(enumType: Disponibilite_enums::class)]
    private ?Disponibilite_enums $disponibilite = null;

    #[ORM\Column(nullable: true)]
    private ?int $priorite = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $dateAjout = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductPlan::class, cascade: ['persist', 'remove'])]
    private Collection $productPlans;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeProductId = null;

    #[ORM\Column(length: 20)]
    private string $stripeSyncStatus = 'pending';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeSyncError = null;

    public function __construct()
    {
        $this->productPlans = new ArrayCollection();
    }

    public function getStripeProductId(): ?string
    {
        return $this->stripeProductId;
    }

    public function setStripeProductId(?string $stripeProductId): static
    {
        $this->stripeProductId = $stripeProductId;
        return $this;
    }

    public function getStripeSyncStatus(): string
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

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function setCategoryId(int $categoryId): static
    {
        $this->categoryId = $categoryId;
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

    public function getDisponibilite(): ?Disponibilite_enums
    {
        return $this->disponibilite;
    }

    public function setDisponibilite(Disponibilite_enums $disponibilite): static
    {
        $this->disponibilite = $disponibilite;
        return $this;
    }

    public function getPriorite(): ?int
    {
        return $this->priorite;
    }

    public function setPriorite(?int $priorite): static
    {
        $this->priorite = $priorite;
        return $this;
    }

    public function getDateAjout(): ?\DateTime
    {
        return $this->dateAjout;
    }

    public function setDateAjout(\DateTime $dateAjout): static
    {
        $this->dateAjout = $dateAjout;
        return $this;
    }

    /** @return Collection<int, ProductPlan> */
    public function getProductPlans(): Collection
    {
        return $this->productPlans;
    }

    public function addProductPlan(ProductPlan $productPlan): static
    {
        if (!$this->productPlans->contains($productPlan)) {
            $this->productPlans->add($productPlan);
            $productPlan->setProduct($this);
        }

        return $this;
    }

    public function removeProductPlan(ProductPlan $productPlan): static
    {
        if ($this->productPlans->removeElement($productPlan)) {
            if ($productPlan->getProduct() === $this) {
                $productPlan->setProduct(null);
            }
        }

        return $this;
    }
}