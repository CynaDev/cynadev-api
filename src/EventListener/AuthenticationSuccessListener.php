<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpFoundation\Cookie;

class AuthenticationSuccessListener
{
    public function __construct(private int $jwtTtl) {}
    // public function onAuthenticationSuccessResponse(AuthenticationSuccessEvent $event): void
    // {
    //     $data = $event->getData();
    //     /** @var \App\Entity\User $user */
    //     $user = $event->getUser();

    //     if (!$user instanceof UserInterface) {
    //         return;
    //     }

    //     $data['user'] = [
    //         'id' => $user->getId(),
    //         'email' => $user->getEmail(),
    //         'roles' => $user->getRoles(),
    //         'firstname' => $user->getFirstname(),
    //         'lastname' => $user->getLastname(),
    //     ];

    //     $event->setData($data);
    // }

     public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
      {
          $data = $event->getData();
          $token = $data['token'];

          $cookie = Cookie::create('authToken')
              ->withValue($token)
              ->withExpires(time() + $this->jwtTtl)
              ->withPath('/')
              ->withSecure(false) // Set to true in production with HTTPS       
              ->withHttpOnly(true)     
              ->withSameSite('strict');

          $response = $event->getResponse();
          $response->headers->setCookie($cookie);

          $event->setData($data);
      }
}