<?php

namespace App\EventListener;

use App\Entity\Cart;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;

#[AsEntityListener(event: Events::postPersist, entity: User::class)]
class UserCreatedListener
{
    public function __construct(private EntityManagerInterface $em) {}

    public function postPersist(User $user): void
    {
        $cart = new Cart();
        $cart->setUser($user);
        $cart->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($cart);
        $this->em->flush();
    }
}
