<?php
// api/src/Service/EmailService.php

namespace App\Service;

use App\Entity\Token;
use App\Entity\User;
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
            rtrim('localhost:5173', '/'),
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
}