<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Config\Disponibilite_enums;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;


#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ApiResource(
    paginationEnabled: true,
    paginationItemsPerPage: 5,
    paginationClientItemsPerPage: true,
    paginationMaximumItemsPerPage: 50
)]
#[ApiFilter(SearchFilter::class, properties: ['categoryId' => 'exact', 'priorite' => 'exact', 'name' => 'partial', 'description' => 'partial'])]
#[ApiFilter(RangeFilter::class, properties: ['price'])]
#[ApiFilter(OrderFilter::class, properties: ['price', 'priorite', 'dateAjout'], arguments: ['orderParameterName' => 'order'])]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $categoryId = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(enumType: Disponibilite_enums::class)]
    private ?Disponibilite_enums $disponibilite = null;

    #[ORM\Column]
    private bool $priorite = false;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $dateAjout = null;

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

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
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

    public function isPriorite(): bool
    {
        return $this->priorite;
    }

    public function setPriorite(bool $priorite): static
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
}
