<?php

namespace App\Controller;

use App\Entity\Token;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class VerifyEmailController extends AbstractController
{
    public function __construct(
        private TokenService $tokenService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/api/verify-email', name: 'api_verify_email', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $tokenValue = $data['token'] ?? null;

        if (!$tokenValue) {
            return new JsonResponse(['error' => 'Token manquant'], 400);
        }

        $token = $this->tokenService->validateToken($tokenValue, Token::TYPE_EMAIL_VERIFICATION);

        if (!$token) {
            return new JsonResponse(['error' => 'Token invalide ou expiré'], 400);
        }

        $user = $token->getUser();
        $user->setIsVerified(true); 

        $this->tokenService->markTokenAsUsed($token);

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Email vérifié avec succès']);
    }
}