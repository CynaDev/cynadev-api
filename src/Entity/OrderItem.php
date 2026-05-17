<?php
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order:read'])]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null;

    #[ORM\Column]
    #[Groups(['order:read'])]
    private int $quantity = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['order:read'])]
    private ?string $price = null; // prix unitaire au moment de la commande

    #[ORM\ManyToOne(targetEntity: ProductPlan::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['order:read'])]
    private ?ProductPlan $productPlan = null;
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }
    public function setProduct(?Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }
    public function setOrder(?Order $order): self
    {
        $this->order = $order;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }
    public function setPrice(string $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getProductPlan(): ?ProductPlan
    {
        return $this->productPlan;
    }
    public function setProductPlan(?ProductPlan $productPlan): self
    {
        $this->productPlan = $productPlan;
        return $this;
    }
}