<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/auth', name: 'auth')]
class CheckCredentialsController extends AbstractController
{
    // #[Route('/check-login', name: 'check_credentials', methods: ['POST'])]
    // public function __invoke(
    //     Request $request,
    //     UserRepository $users,
    //     UserPasswordHasherInterface $passwordHasher
    // ): Response {
    //     $data = json_decode($request->getContent(), true) ?? [];

    //     $email = $data['email'] ?? null;
    //     $plainPassword = $data['password'] ?? null;

    //     if (!$email || !$plainPassword) {
    //         return $this->json(
    //             ['valid' => false, 'error' => 'missing email or password'],
    //             Response::HTTP_BAD_REQUEST
    //         );
    //     }

    //     $user = $users->findOneBy(['email' => $email]);

    //     $valid = $user && $passwordHasher->isPasswordValid($user, $plainPassword);

    //     return $this->json(['valid' => $valid]);
    // }

    // #[Route('/sign-in', name: 'sign-in', methods: ['POST'])]
    // public function signIn(
    //     Request $request,
    //     UserRepository $userRespository,
    //     UserPasswordHasherInterface $passwordHasher,
    //     JWTTokenManagerInterface $jwtManager
    // ): JsonResponse {
    //     $data = json_decode($request->getContent(), true);

    //     if (!isset($data['email'], $data['password'])) {
    //         return new JsonResponse([
    //             'message' => 'Missing required fields (email or password',
    //         ], Response::HTTP_BAD_REQUEST);
    //     }

    //     /**
    //      * @var \App\Entity\User
    //      */
    //     $user = $userRespository->findOneBy(criteria: ['email' => $data['email']]);

    //     if (!$user || !$passwordHasher->isPasswordValid(user: $user, plainPassword: $data['password'])) {
    //         return new JsonResponse([
    //             'message' => 'Invalid credetials'
    //         ], Response::HTTP_FORBIDDEN);
    //     }

       
    //     $token = $jwtManager->create($user);

    //     return new JsonResponse([
    //         'token' => $token
    //     ]);
    // }

}
