<?php

namespace App\Controller;

use App\Repository\AddressRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api')]
#[IsGranted('ROLE_USER')] // JWT requis sur toutes les routes du controller
class AddressController extends AbstractController
{
    public function __construct(
        private AddressRepository $addressRepository,
        private UserRepository $userRepository,
        private SerializerInterface $serializer,
    ) {}

    #[Route('/users/{userId}/addresses', methods: ['GET'])]
    public function getAddressesByUser(int $userId): JsonResponse
    {
        $currentUser = $this->getUser();

        if (!$this->isGranted('ROLE_ADMIN') && ($currentUser instanceof User) && $currentUser->getId() !== $userId) {
            throw new AccessDeniedHttpException("Vous n'êtes pas autorisé à accéder à ces adresses.");
        }

        $user = $this->userRepository->find($userId);

        if (!$user) {
            throw new NotFoundHttpException("Utilisateur $userId introuvable.");
        }

        $addresses = $this->addressRepository->findBy(['user' => $user]);

        $data = $this->serializer->serialize(
            $addresses,
            'json',
            ['groups' => ['address:read']]
        );

        return new JsonResponse($data, 200, [], true);
    }

    #[Route('/users/{userId}/addresses/{id}', methods: ['GET'])]
    public function getAddress(int $userId, int $id): JsonResponse
    {
        $currentUser = $this->getUser();

        if (!$this->isGranted('ROLE_ADMIN') && ($currentUser instanceof User) && $currentUser->getId() !== $userId) {
            throw new AccessDeniedHttpException("Vous n'êtes pas autorisé à accéder à cette adresse.");
        }

        $address = $this->addressRepository->findOneBy(['id' => $id, 'user' => $userId]);

        if (!$address) {
            throw new NotFoundHttpException("Adresse introuvable.");
        }

        $data = $this->serializer->serialize($address, 'json', ['groups' => ['address:read']]);

        return new JsonResponse($data, 200, [], true);
    }
}