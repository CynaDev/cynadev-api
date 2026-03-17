<?php

namespace App\Entity;

use App\Repository\HomepageRepository;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\ApiResource;

#[ORM\Entity(repositoryClass: HomepageRepository::class)]
#[ORM\Table(name: 'homepage')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
class Homepage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titleprincipal = null;

    #[ORM\Column(length: 255)]
    private ?string $titlewordbold = null;

    #[ORM\Column(length: 255)]
    private ?string $subtitle = null;

    #[ORM\Column(length: 255)]
    private ?string $firstbutton = null;

    #[ORM\Column(length: 255)]
    private ?string $firstbuttonlink = null;

    #[ORM\Column(length: 255)]
    private ?string $secondbutton = null;

    #[ORM\Column(length: 255)]
    private ?string $secondButtonlink = null;

    #[ORM\Column(length: 255)]
    private ?string $bannercentral = null;

    #[ORM\Column(length: 255)]
    private ?string $subtitlebanner = null;

    #[ORM\Column(length: 255)]
    private ?string $bannercta = null;

    #[ORM\Column(length: 255)]
    private ?string $pubtitle = null;

    #[ORM\Column(length: 255)]
    private ?string $pubsubtitle = null;

    #[ORM\Column(length: 255)]
    private ?string $pubdesc = null;

    #[ORM\Column(length: 255)]
    private ?string $pubbutton = null;

    #[ORM\Column(length: 255)]
    private ?string $publinkbutton = null;


    public function __construct()
    {
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitleprincipal(): ?string
    {
        return $this->titleprincipal;
    }
    public function setTitleprincipal(string $titleprincipal): static
    {
        $this->titleprincipal = $titleprincipal;
        return $this;
    }

    public function getTitlewordbold(): ?string
    {
        return $this->titlewordbold;
    }
    public function setTitlewordbold(string $titlewordbold): static
    {
        $this->titlewordbold = $titlewordbold;
        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }
    public function setSubtitle(string $subtitle): static
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    public function getFirstbutton(): ?string
    {
        return $this->firstbutton;
    }
    public function setFirstbutton(string $firstbutton): static
    {
        $this->firstbutton = $firstbutton;
        return $this;
    }

    public function getFirstbuttonlink(): ?string
    {
        return $this->firstbuttonlink;
    }
    public function setFirstbuttonlink(string $firstbuttonlink): static
    {
        $this->firstbuttonlink = $firstbuttonlink;
        return $this;
    }

    public function getSecondbutton(): ?string
    {
        return $this->secondbutton;
    }
    public function setSecondbutton(string $secondbutton): static
    {
        $this->secondbutton = $secondbutton;
        return $this;
    }

    public function getSecondbuttonlink(): ?string
    {
        return $this->secondButtonlink;
    }
    public function setSecondbuttonlink(string $secondButtonlink): static
    {
        $this->secondButtonlink = $secondButtonlink;
        return $this;
    }

    public function getBannercentral(): ?string
    {
        return $this->bannercentral;
    }
    public function setBannercentral(string $bannercentral): static
    {
        $this->bannercentral = $bannercentral;
        return $this;
    }

    public function getSubtitlebanner(): ?string
    {
        return $this->subtitlebanner;
    }
    public function setSubtitlebanner(string $subtitlebanner): static
    {
        $this->subtitlebanner = $subtitlebanner;
        return $this;
    }

    public function getBannercta(): ?string
    {
        return $this->bannercta;
    }
    public function setBannercta(string $bannercta): static
    {
        $this->bannercta = $bannercta;
        return $this;
    }

    public function getPubtitle(): ?string
    {
        return $this->pubtitle;
    }
    public function setPubtitle(string $pubtitle): static
    {
        $this->pubtitle = $pubtitle;
        return $this;
    }

     public function getPubsubtitle(): ?string
    {
        return $this->pubsubtitle;
    }
    public function setPubsubtitle(string $pubsubtitle): static
    {
        $this->pubsubtitle = $pubsubtitle;
        return $this;
    }

    public function getPubdesc(): ?string
    {
        return $this->pubdesc;
    }
    public function setPubdesc(string $pubdesc): static
    {
        $this->pubdesc = $pubdesc;
        return $this;
    }

    public function getPubbutton(): ?string
    {
        return $this->pubbutton;
    }
    public function setPubbutton(string $pubbutton): static
    {
        $this->pubbutton = $pubbutton;
        return $this;
    }

    public function getPublinkbutton(): ?string
    {
        return $this->publinkbutton;
    }
    public function setPublinkbutton(string $publinkbutton): static
    {
        $this->publinkbutton = $publinkbutton;
        return $this;
    }

}
