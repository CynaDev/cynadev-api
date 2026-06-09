<?php
// api/src/Service/TokenService.php

namespace App\Service;

use App\Entity\Token;
use App\Entity\User;
use App\Repository\TokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class TokenService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenRepository $tokenRepository,
        private readonly LoggerInterface $logger
    ) {}

    public function createToken(
        User $user, 
        string $type, 
        ?\DateInterval $validity = null,
        ?array $metadata = null
    ): Token {
        // Invalider les anciens tokens du même type
        $invalidatedCount = $this->tokenRepository->invalidateUserTokensByType($user, $type);
        
        if ($invalidatedCount > 0) {
            $this->logger->info('Tokens précédents invalidés', [
                'user_id' => $user->getId(),
                'type' => $type,
                'count' => $invalidatedCount
            ]);
        }

        if ($validity === null) {
            $validity = match($type) {
                Token::TYPE_EMAIL_VERIFICATION => new \DateInterval('P1D'), // 1 jour
                Token::TYPE_PASSWORD_RESET => new \DateInterval('PT1H'), // 1 heure
                Token::TYPE_REFRESH_TOKEN => new \DateInterval('P30D'), // 30 jours
                default => new \DateInterval('PT1H')
            };
        }

        $token = new Token();
        $token->setToken(Uuid::v4()->toRfc4122());
        $token->setType($type);
        $token->setUser($user);
        $expiresAt = new \DateTime();
        $expiresAt->add($validity);
        $token->setExpiresAt($expiresAt);        
        if ($metadata) {
            $token->setMetadata($metadata);
        }

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        $this->logger->info('Token créé', [
            'token_id' => $token->getId(),
            'user_id' => $user->getId(),
            'type' => $type
        ]);

        return $token;
    }

    public function validateToken(string $tokenValue, string $type): ?Token
    {
        return $this->tokenRepository->findValidToken($tokenValue, $type);
    }

    public function findValidTokenByMetadata(User $user, string $type, string $metadataKey, string $metadataValue): ?Token
    {
        return $this->tokenRepository->findValidTokenByUserTypeAndMetadata($user, $type, $metadataKey, $metadataValue);
    }

    public function markTokenAsUsed(Token $token): void
    {
        $token->setIsUsed(true);
        $this->entityManager->flush();
        
        $this->logger->info('Token marqué comme utilisé', [
            'token_id' => $token->getId()
        ]);
    }

    public function cleanupExpiredTokens(): int
    {
        return $this->tokenRepository->deleteExpiredTokens();
    }

    public function hasActiveToken(User $user, string $type): bool
    {
        return $this->tokenRepository->countActiveTokensByType($user, $type) > 0;
    }
}