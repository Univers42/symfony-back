<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;

final class AdminAccessTest extends WebTestCase
{
    private static function ensureAdmin(KernelBrowser $client): User
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();
        $repo = $em->getRepository(User::class);
        $user = $repo->findOneBy(['email' => 'admintest@example.test']);
        if ($user instanceof User) {
            return $user;
        }
        $user = new User();
        $user->setEmail('admintest@example.test');
        $user->setRoles(['ROLE_ADMIN']);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'admin1234'));
        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testAnonymousAdminRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');
        $this->assertResponseRedirects('http://localhost/login');
    }

    public function testRegisterPageIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Créer un compte');
    }

    public function testLoginPageIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        $this->assertResponseIsSuccessful();
    }

    public function testAdminAccessibleWithRoleAdmin(): void
    {
        $client = static::createClient();
        $admin = self::ensureAdmin($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin');
        // Dashboard redirects to the first CRUD page.
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Quai Antique', $client->getResponse()->getContent());
    }

    public function testAdminRestaurantCrudIndex(): void
    {
        $client = static::createClient();
        $admin = self::ensureAdmin($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/restaurant');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Le Quai Antique', $client->getResponse()->getContent());
    }

    public function testAdminUserCrudIndex(): void
    {
        $client = static::createClient();
        $admin = self::ensureAdmin($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/user');
        $this->assertResponseIsSuccessful();
    }

    public function testRegisterCreatesUser(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $email = 'newuser_' . bin2hex(random_bytes(4)) . '@test.local';

        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton("S'inscrire")->form();
        $form['email']        = $email;
        $form['display_name'] = 'New User';
        $form['password']     = 'secret123';
        $client->submit($form);

        $this->assertResponseRedirects('/login');

        $created = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($created);
        $this->assertContains('ROLE_CLIENT', $created->getRoles());
    }
}
