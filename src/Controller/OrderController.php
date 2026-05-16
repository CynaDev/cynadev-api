<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends AbstractController
{
    #[Route('/api/users/{id}/orders', methods: ['GET'])]
    public function getOrdersByUser(
        int $id,
        UserRepository $userRepository,
        OrderRepository $orderRepository,
        SerializerInterface $serializer,
    ): JsonResponse {
        $user = $userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur introuvable'], 404);
        }

        $orders = $orderRepository->findBy(
            ['user' => $user],
            ['dateCommande' => 'DESC'],
        );

        $data = $serializer->serialize($orders, 'json', ['groups' => ['order:read']]);

        return new JsonResponse($data, 200, [], true);
    }

    #[Route('/api/orders/{id}/invoice', name: 'order_invoice', methods: ['GET'])]
    public function downloadInvoice(int $id, OrderRepository $orderRepository): Response
    {
        $order = $orderRepository->find($id);

        if (!$order || ($order->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN'))) {                                           
            throw $this->createNotFoundException();                                                                                             
        } 

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($this->renderView('invoice/invoice.html.twig', [
            'order' => $order,
        ]));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdf = $dompdf->output();

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="facture-' . $order->getId() . '.pdf"',
        ]);
    }
}
