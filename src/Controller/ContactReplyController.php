<?php
namespace App\Controller;

use App\Entity\Contact;
use App\Repository\ContactRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


class ContactReplyController extends AbstractController
{
    #[Route('/api/admin/contact-reply', name: 'admin_contact_reply', methods: ['POST'])]
    public function __invoke(Request $request, ContactRepository $contactRepository, EmailService $emailService, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $contactId = $data['contactId'] ?? null;
        $reply = $data['reply'] ?? null;

        if (!$contactId || $reply === null) {
            return new JsonResponse(['error' => 'Données manquantes'], 400);
        }

        $contact = $contactRepository->find($contactId);
        if (!$contact instanceof Contact) {
            return new JsonResponse(['error' => 'Contact introuvable'], 404);
        }

        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getRoles') || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        // Enregistrer la réponse dans la base
        $contact->setResponse($reply);
        $contact->setResponderEmail($user->getEmail());
        $contact->setRespondedAt(new \DateTimeImmutable());
        $em->persist($contact);
        $em->flush();

        // Envoyer le mail
        $sent = $emailService->sendContactReplyEmail($user, $contact, $reply);

        if (!$sent) {
            return new JsonResponse(['error' => 'Impossible d\'envoyer le mail'], 500);
        }

        return new JsonResponse(['success' => true]);
    }
}
