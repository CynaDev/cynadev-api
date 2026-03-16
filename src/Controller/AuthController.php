<?php
namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AuthController extends AbstractController
{
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/verify-token', name: 'verify_token', methods: ['GET'])]
    public function verifyToken(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'valid' => true,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'isAdmin' => in_array('ROLE_ADMIN', $user->getRoles()),
            ]
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/api/me', name: 'users_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id'        => $user->getId(),
            'email'     => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastname'  => $user->getLastName(),
            'roles'     => $user->getRoles(),
            'orders'    => [],
        ]);
    }

    #[Route('/api/logout', methods: ['POST'])]
  public function logout(Response $response): Response
  {
      $response->headers->clearCookie(
        'authToken', 
        '/',
        null,
        true,
        true, 
        'strict');
      return $this->json(['message' => 'Déconnecté'], 200);
  }
}