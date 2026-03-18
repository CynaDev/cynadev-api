<?php
namespace App\EventListener;

use App\Entity\Stock;
use App\Service\EmailService; // <-- On importe ton service
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: Stock::class)]
class StockAlertListener
{
    public function __construct(
        private readonly EmailService $emailService 
    ) {}

    public function preUpdate(Stock $stock, PreUpdateEventArgs $event): void
    {
        // On vérifie si la quantité a changé
        if (!$event->hasChangedField('quantite')) {
            return;
        }

        $nouvelleQuantite = $event->getNewValue('quantite');
        $ancienneQuantite = $event->getOldValue('quantite');

        // Si ça tombe à 0 (et que ce n'était pas déjà à 0)
        if ($nouvelleQuantite <= 0 && $ancienneQuantite > 0) {
            $produit = $stock->getProduct();
            
            if ($produit) {
                $this->emailService->sendStockAlertEmail($produit);
            }
        }
    }
}