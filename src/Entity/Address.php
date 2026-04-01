<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\AddressRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AddressRepository::class)]
#[ApiResource]
class Address
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['address:read'])]
    private ?int $id = null;


    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'address')]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    #[Groups(['address:read'])]
    private ?string $num = null;

    #[ORM\Column(length: 255)]
    #[Groups(['address:read'])]
    private ?string $rue = null;

    #[ORM\Column]
    #[Groups(['address:read'])]
    private ?int $cp = null;

    #[ORM\Column(length: 255)]
    #[Groups(['address:read'])]
    private ?string $compRue = null;

    #[ORM\Column(length: 255)]
    #[Groups(['address:read'])]
    private ?string $ville = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getNum(): ?string
    {
        return $this->num;
    }

    public function setNum(string $num): static
    {
        $this->num = $num;

        return $this;
    }

    public function getRue(): ?string
    {
        return $this->rue;
    }

    public function setRue(string $rue): static
    {
        $this->rue = $rue;

        return $this;
    }

    public function getCp(): ?int
    {
        return $this->cp;
    }

    public function setCp(int $cp): static
    {
        $this->cp = $cp;

        return $this;
    }

    public function getCompRue(): ?string
    {
        return $this->compRue;
    }

    public function setCompRue(string $compRue): static
    {
        $this->compRue = $compRue;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }
}
