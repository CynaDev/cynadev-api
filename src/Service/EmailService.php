<?php
// api/src/Service/EmailService.php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Token;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use App\Entity\Product;


class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly Environment $twig,
        private readonly string $mailFrom = 'noreply@cynadev.fr'
    ) {}

    public function sendVerificationEmail(User $user, Token $token): bool
    {
        $emailAddress = $user->getEmail();

        if (!$emailAddress) {
            $this->logger->warning('Impossible d\'envoyer le mail de vérification : email manquant', [
                'user_id' => $user->getId()
            ]);
            return false;
        }

        $verificationUrl = sprintf(
            '%s/verify-email?token=%s',
            rtrim('cynadev.fr', '/'),
            $token->getToken()
        );

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($user->getEmail())
            ->subject('Vérification de votre email - Cynadev')
            ->html($this->twig->render('emails/VerificationEmail.html.twig', [
                'username' => $user->getUserIdentifier(),
                'verificationUrl' => $verificationUrl
            ]));

        try {
            $this->mailer->send($email);
            $this->logger->info('Email de vérification envoyé', [
                'email' => $emailAddress,
                'token_id' => $token->getId(),
                'user_id' => $user->getId()
            ]);
            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Impossible d\'envoyer le mail de vérification', [
                'email' => $emailAddress,
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendOrderConfirmationEmail(User $user, Order $order, array $cartItems = []): bool
    {
        $emailAddress = $user->getEmail();

        if (!$emailAddress) {
            $this->logger->warning('Email de confirmation non envoyé : email manquant', [
                'user_id' => $user->getId(),
                'order_id' => $order->getId(),
            ]);
            return false;
        }

        $lines = array_map(function ($item) {
            $plan    = $item->getProductPlan();
            $name    = $plan?->getProduct()?->getName() ?? 'Produit';
            $cycle   = $plan?->getBillingCycle() === 'annuel' ? 'Annuel' : 'Mensuel';
            $unitHt  = round((float) $item->getUnitPrice() / 1.2, 2);
            $totalHt = round($unitHt * $item->getQuantity(), 2);

            return [
                'name'     => $name,
                'cycle'    => $cycle,
                'quantity' => $item->getQuantity(),
                'unitHt'   => number_format($unitHt, 2, ',', ' '),
                'totalHt'  => number_format($totalHt, 2, ',', ' '),
            ];
        }, $cartItems);

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($emailAddress)
            ->subject('Confirmation de votre commande #' . $order->getId() . ' - Cynadev')
            ->html($this->twig->render('emails/OrderConfirmationEmail.html.twig', [
                'username' => $user->getFirstName() ?: $emailAddress,
                'orderId'  => $order->getId(),
                'date'     => $order->getDateCommande()?->format('d/m/Y'),
                'lines'    => $lines,
                'totalHt'  => number_format((float) $order->getTotalHt(), 2, ',', ' '),
                'totalTtc' => number_format((float) $order->getTotalTtc(), 2, ',', ' '),
            ]));

        try {
            $this->mailer->send($email);
            $this->logger->info('Email de confirmation de commande envoyé', [
                'email'    => $emailAddress,
                'order_id' => $order->getId(),
            ]);
            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Impossible d\'envoyer le mail de confirmation', [
                'email'    => $emailAddress,
                'order_id' => $order->getId(),
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function sendStockAlertEmail(Product $product): bool
    {
        // le passer dans le .env
        $destinataires = 'baertjoseph8@gmail.com, arthur.duval18@gmail.com, theo.gandon9@gmail.com'; 


        $emailsArray = explode(',', $destinataires);

        // On enlève les espaces vides autour des emails
        $emailsArray = array_map('trim', $emailsArray);

        $email = (new Email())
            ->from($this->mailFrom)
            ->to(...$emailsArray)
            ->subject('⚠️ Alerte Rupture de Stock : ' . $product->getName())
            ->html($this->twig->render('emails/StockAlertEmail.html.twig', [
                'product' => $product
            ]));

        try {
            $this->mailer->send($email);
            $this->logger->info('Email d\'alerte de stock envoyé', [
                'product_id' => $product->getId(),
                'product_name' => $product->getName()
            ]);
            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Impossible d\'envoyer l\'alerte de stock', [
                'product_id' => $product->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}