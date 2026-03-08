<?php
// api/src/State/UserPasswordHasher.php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Token;
use App\Entity\User;
use App\Service\EmailService;
use App\Service\TokenService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Psr\Log\LoggerInterface;

/**
 * @implements ProcessorInterface<User, User|void>
 */
final readonly class UserPasswordHasher implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private UserPasswordHasherInterface $passwordHasher,
        private TokenService $tokenService,
        private EmailService $emailService,
        private LoggerInterface $logger
    ) {}

    /**
     * @param User $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        if (!$data->getPlainPassword()) {
            return $this->processor->process($data, $operation, $uriVariables, $context);
        }

        $hashedPassword = $this->passwordHasher->hashPassword(
            $data,
            $data->getPlainPassword()
        );
        $data->setPassword($hashedPassword);
        $data->setPlainPassword(null);
        
        if ($operation instanceof Post) {
            $data->setIsVerified(false);
        }
        
        $result = $this->processor->process($data, $operation, $uriVariables, $context);
        
        if ($operation instanceof Post) {
    try {
        // Log pour vérifier que le code passe bien ici
        $this->logger->info('DÉBUT GÉNÉRATION TOKEN POUR USER: ' . $data->getId());

        $token = $this->tokenService->createToken($data, Token::TYPE_EMAIL_VERIFICATION);
        $sent = $this->emailService->sendVerificationEmail($data, $token);

        if (!$sent) {
            $this->logger->error('Le service mail a retourné FALSE');
        }
    } catch (\Exception $e) {
        $this->logger->error('EXCEPTION FATALE: ' . $e->getMessage());
        throw $e; 
    }
}
        
        return $result;
    }
}