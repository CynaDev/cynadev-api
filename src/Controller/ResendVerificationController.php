<?php

namespace App\Controller;

use App\Entity\Token;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\TokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ResendVerificationController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private TokenService $tokenService,
        private EmailService $emailService
    ) {}

    #[Route('/api/resend-verification', name: 'api_resend_verification', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return new JsonResponse(['error' => 'L\'email est obligatoire.'], 400);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        
        if (!$user) {
            return new JsonResponse(['message' => 'Si ce compte existe, un email a été envoyé.']);
        }

        if ($user->isVerified()) {
            return new JsonResponse(['error' => 'Ce compte est déjà activé.'], 400);
        }

        $token = $this->tokenService->createToken($user, Token::TYPE_EMAIL_VERIFICATION);
        
        $this->emailService->sendVerificationEmail($user, $token);

        return new JsonResponse(['message' => 'Si ce compte existe, un email a été envoyé.']);
    }
}