<?php

namespace App\Service;

use App\Entity\Contact;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\Token;
use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly Environment $twig,
        private readonly UserRepository $userRepository,
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

    public function sendAdminTwoFactorCode(User $user, string $code): bool
    {
        $emailAddress = $user->getEmail();

        if (!$emailAddress) {
            $this->logger->warning('Impossible d\'envoyer le mail 2FA admin : email manquant', [
                'user_id' => $user->getId()
            ]);
            return false;
        }

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($emailAddress)
            ->subject('Code de connexion administrateur - Cynadev')
            ->html($this->twig->render('emails/AdminTwoFactorCodeEmail.html.twig', [
                'firstName' => $user->getFirstName() ?? '',
                'lastName' => $user->getLastName() ?? '',
                'username' => $user->getUserIdentifier(),
                'code' => $code,
            ]));

        try {
            $this->mailer->send($email);
            $this->logger->info('Email 2FA admin envoyé', [
                'email' => $emailAddress,
                'user_id' => $user->getId(),
            ]);
            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Impossible d\'envoyer le mail 2FA admin', [
                'email' => $emailAddress,
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
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
            $plan = $item->getProductPlan();
            $product = $plan?->getProduct();

            $name = $product?->getName() ?? 'Produit';
            $rawCycle = $plan?->getBillingCycle();
            $cycle = match ($rawCycle) {
                'annuel' => 'Annuel',
                'mensuel' => 'Mensuel',
                default => '—',
            };

            $unitTtc = (float) $item->getUnitPrice();
            $unitHt = round($unitTtc / 1.2, 2);
            $totalHt = round($unitHt * $item->getQuantity(), 2);

            return [
                'name' => $name,
                'cycle' => $cycle,
                'quantity' => $item->getQuantity(),
                'unitHt' => number_format($unitHt, 2, ',', ' '),
                'totalHt' => number_format($totalHt, 2, ',', ' '),
            ];
        }, $cartItems);

        $subtotalTtc = (float) ($order->getSubtotalTtc() ?? $order->getTotalTtc() ?? 0);
        $discountTtc = (float) ($order->getDiscountTtc() ?? 0);
        $totalHt = (float) ($order->getTotalHt() ?? 0);
        $totalTtc = (float) ($order->getTotalTtc() ?? 0);
        $tva = round($totalTtc - $totalHt, 2);

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($emailAddress)
            ->subject('Confirmation de votre commande #' . $order->getId() . ' - Cynadev')
            ->html($this->twig->render('emails/OrderConfirmationEmail.html.twig', [
                'username' => $user->getFirstName() ?: $emailAddress,
                'orderId' => $order->getId(),
                'date' => $order->getDateCommande()?->format('d/m/Y'),
                'lines' => $lines,
                'subtotalTtc' => number_format($subtotalTtc, 2, ',', ' '),
                'discountTtc' => number_format($discountTtc, 2, ',', ' '),
                'discountTtcFloat' => $discountTtc,
                'promoCode' => $order->getPromoCode(),
                'totalHt' => number_format($totalHt, 2, ',', ' '),
                'totalTtc' => number_format($totalTtc, 2, ',', ' '),
                'tva' => number_format($tva, 2, ',', ' '),
            ]));

        try {
            $this->mailer->send($email);
            $this->logger->info('Email de confirmation de commande envoyé', [
                'email' => $emailAddress,
                'order_id' => $order->getId(),
            ]);
            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Impossible d\'envoyer le mail de confirmation', [
                'email' => $emailAddress,
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function sendStockAlertEmail(Product $product): bool
    {
        $admins = $this->userRepository->findAdmins();

        if (empty($admins)) {
            $this->logger->warning('Aucun administrateur trouvé pour envoyer l\'alerte de stock', [
                'product_id' => $product->getId(),
                'product_name' => $product->getName()
            ]);
            return false;
        }

        $emailsArray = array_map(function (User $admin) {
            return $admin->getEmail();
        }, $admins);

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
                'product_name' => $product->getName(),
                'admin_count' => count($emailsArray)
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

    public function sendContactReplyEmail(User $admin, Contact $contact, string $reply): bool
    {
        $to = $contact->getEmail();
        if (!$to) {
            $this->logger->warning('Contact sans email, impossible d\'envoyer la réponse', [
                'contact_id' => $contact->getId(),
            ]);
            return false;
        }

        $subject = 'Re : ' . $contact->getSujet();

        $html = $this->twig->render('emails/ContactReplyEmail.html.twig', [
            'contact' => $contact,
            'reply' => $reply,
            'admin' => $admin,
        ]);

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($to)
            ->cc($admin->getEmail())
            ->subject($subject)
            ->html($html);

        try {
            $this->mailer->send($email);
            $this->logger->info('Réponse au contact envoyée', [
                'contact_id' => $contact->getId(),
                'to' => $to,
                'admin' => $admin->getId(),
            ]);
            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Impossible d\'envoyer la réponse au contact', [
                'contact_id' => $contact->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}