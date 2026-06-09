<?php

namespace App\Controller;

use App\Entity\Token;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\TokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminTwoFactorAuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TokenService $tokenService,
        private readonly EmailService $emailService,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[Route('/api/admin/2fa/request', name: 'admin_2fa_request', methods: ['POST'])]
    public function requestCode(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], 401);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = $this->tokenService->createToken(
            $user,
            Token::TYPE_ADMIN_2FA,
            new \DateInterval('PT10M'),
            ['code' => $code]
        );

        if (!$this->emailService->sendAdminTwoFactorCode($user, $code)) {
            return $this->json(['error' => 'Impossible d\'envoyer le code de vérification.'], 500);
        }

        return $this->json([
            'success' => true,
            'message' => 'Code 2FA envoyé par email. Il est valide 10 minutes.',
            'expiresAt' => $token->getExpiresAt()->format('c'),
        ]);
    }

    #[Route('/api/admin/2fa/verify', name: 'admin_2fa_verify', methods: ['POST'])]
    public function verifyCode(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || empty($data['code'])) {
            return $this->json(['error' => 'Code requis.'], 400);
        }

        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], 401);
        }

        $token = $this->tokenService->findValidTokenByMetadata($user, Token::TYPE_ADMIN_2FA, 'code', $data['code']);

        if (!$token) {
            return $this->json(['error' => 'Code invalide ou expiré.'], 401);
        }

        $this->tokenService->markTokenAsUsed($token);

        return $this->json([
            'success' => true,
            'message' => 'Vérification réussie. Vous pouvez maintenant accéder au dashboard admin.',
        ]);
    }
}
