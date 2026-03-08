<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException; // Alias pour plus de clarté
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse; // Utiliser JsonResponse
use Symfony\Component\Routing\Annotation\Route;

class TestDatabaseController extends AbstractController
{
    #[Route('/test-db', name: 'test_database')]
    public function index(Connection $connection): JsonResponse
    {
        try {
            // Exécute une requête simple pour obtenir la version PostgreSQL
            $version = $connection->executeQuery('SELECT version()')->fetchOne();

            // Retourner une réponse JSON en cas de succès
            return new JsonResponse([
                'status' => 'success',
                'message' => 'Connexion à la base de données PostgreSQL réussie.',
                'version' => $version,
            ]);
        } catch (DBALException $e) {
            // Capter spécifiquement les exceptions de base de données
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Erreur de connexion à la base de données.',
                'details' => $e->getMessage(),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR); // Code HTTP 500
        }
    }
}