<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ImageController extends AbstractController
{
    #[Route('/api/upload-image', name: 'upload_image', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $image */
        $image = $request->files->get('image');
        $name  = $request->request->get('name');
        $type = $request->request->get('type');

        if (!$image) {
            return new JsonResponse(['error' => 'Aucune image reçue'], Response::HTTP_BAD_REQUEST);
        }

        if (!$name) {
            return new JsonResponse(['error' => 'Paramètre "name" manquant'], Response::HTTP_BAD_REQUEST);
        }

        if(!$type){
            return new JsonResponse(['error' => 'Paramètre "type" manquant'], Response::HTTP_BAD_REQUEST);
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($image->getMimeType(), $allowedMimeTypes)) {
            return new JsonResponse(['error' => 'Type de fichier non autorisé'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Utilisation du nom fourni + extension détectée depuis le MIME
        $baseName = pathinfo($name, PATHINFO_FILENAME);
        $filename = $baseName . '.' . $image->guessExtension();
        $destination = $this->getParameter('kernel.project_dir') . '/public/images/'.$type;

        try {
            $image->move($destination, $filename);
        } catch (FileException $e) {
            return new JsonResponse(['error' => 'Erreur lors de la sauvegarde : ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'message'  => 'Image uploadée avec succès',
            'filename' => $filename,
            'url'      => '/images/categories/' . $filename,
        ], Response::HTTP_CREATED);
    }
}
