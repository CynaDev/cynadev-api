<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;

use App\Repository\CartItemRepository;
use App\Entity\ProductPlan;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
#[ApiResource]
class CartItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cart:items'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['cart:items'])]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['cart:items'])]
    private ?string $unitPrice = null;

    #[ORM\ManyToOne(inversedBy: 'cartItems')]
    private ?Cart $cart = null;

    #[ORM\ManyToOne]
    #[Groups(['cart:items'])]
    private ?ProductPlan $productPlan = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): static
    {
        $this->cart = $cart;

        return $this;
    }

    public function getProductPlan(): ?ProductPlan
    {
        return $this->productPlan;
    }

    public function setProductPlan(?ProductPlan $productPlan): static
    {
        $this->productPlan = $productPlan;

        return $this;
    }
}
