<?php
// api/src/Entity/Token.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\TokenRepository;

#[ORM\Entity(repositoryClass: TokenRepository::class)]
#[ORM\Table(name: 'tokens')]
#[ORM\Index(columns: ['token'], name: 'idx_token')]
#[ORM\Index(columns: ['type', 'expires_at'], name: 'idx_type_expires')]
class Token
{
    public const TYPE_EMAIL_VERIFICATION = 'email_verification';
    public const TYPE_PASSWORD_RESET = 'password_reset';
    public const TYPE_REFRESH_TOKEN = 'refresh_token';
    public const TYPE_ADMIN_2FA = 'admin_2fa';
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;
    
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $token;
    
    #[ORM\Column(type: 'string', length: 50)]
    private string $type;
    
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;
    
    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;
    
    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $expiresAt;
    
    #[ORM\Column(type: 'boolean')]
    private bool $isUsed = false;
    
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $usedAt = null;
    
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;
    
    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function getToken(): string
    {
        return $this->token;
    }
    
    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }
    
    public function getUser(): User
    {
        return $this->user;
    }
    
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }
    
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
    
    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    
    public function getExpiresAt(): \DateTimeInterface
    {
        return $this->expiresAt;
    }
    
    public function setExpiresAt(\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }
    
    public function isExpired(): bool
    {
        return new \DateTime() > $this->expiresAt;
    }
    
    public function isUsed(): bool
    {
        return $this->isUsed;
    }
    
    public function setIsUsed(bool $isUsed): self
    {
        $this->isUsed = $isUsed;
        if ($isUsed && !$this->usedAt) {
            $this->usedAt = new \DateTime();
        }
        return $this;
    }
    
    public function getUsedAt(): ?\DateTimeInterface
    {
        return $this->usedAt;
    }
    
    public function setUsedAt(?\DateTimeInterface $usedAt): self
    {
        $this->usedAt = $usedAt;
        return $this;
    }
    
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }
    
    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }
    
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }
}