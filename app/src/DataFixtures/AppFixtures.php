<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = (new User())
            ->setEmail('admin@baas.test')
            ->setRoles(['ROLE_ADMIN'])
            ->setDisplayName('BaaS Administrator');
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin1234'));
        $manager->persist($admin);

        $client = (new User())
            ->setEmail('client@baas.test')
            ->setRoles(['ROLE_CLIENT'])
            ->setDisplayName('Sandbox Client');
        $client->setPassword($this->hasher->hashPassword($client, 'client1234'));
        $manager->persist($client);

        $manager->flush();
    }
}
