<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error'         => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the logout key on the firewall.');
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        UserRepository $users,
        CsrfTokenManagerInterface $csrf,
    ): Response {
        if ($request->isMethod('POST')) {
            $email   = strtolower(trim((string) $request->request->get('email', '')));
            $name    = trim((string) $request->request->get('display_name', '')) ?: null;
            $pwd     = (string) $request->request->get('password', '');
            $token   = (string) $request->request->get('_csrf_token', '');

            if (!$csrf->isTokenValid(new CsrfToken('register', $token))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');
            } elseif (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Email invalide.');
            } elseif (\strlen($pwd) < 8) {
                $this->addFlash('error', 'Le mot de passe doit faire au moins 8 caractères.');
            } elseif ($users->findOneBy(['email' => $email]) !== null) {
                $this->addFlash('error', 'Cet email est déjà inscrit.');
            } else {
                $user = new User();
                $user->setEmail($email);
                $user->setDisplayName($name);
                $user->setRoles(['ROLE_CLIENT']);
                $user->setPassword($hasher->hashPassword($user, $pwd));
                $em->persist($user);
                $em->flush();
                $this->addFlash('success', 'Compte créé. Vous pouvez vous connecter.');

                return $this->redirectToRoute('app_login');
            }

            return $this->render('security/register.html.twig', [
                'last_email'        => $email,
                'last_display_name' => $name,
            ]);
        }

        return $this->render('security/register.html.twig', []);
    }

    #[Route('/account', name: 'app_account', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function account(): Response
    {
        return $this->render('security/account.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}
