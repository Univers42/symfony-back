<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Account-management endpoints. Login itself is handled by the json_login
 * authenticator wired in config/packages/security.yaml; this controller
 * exposes registration and the "who am I" probe.
 */
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Intercepted by the json_login authenticator on the "login" firewall.
        // This stub exists solely so the router knows the path.
        return new JsonResponse(['error' => 'json_login should have intercepted.'], 500);
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = $this->decode($request);

        $constraints = new Assert\Collection([
            'email'        => [new Assert\NotBlank(), new Assert\Email()],
            'password'     => [new Assert\NotBlank(), new Assert\Length(min: 8, max: 4096)],
            'display_name' => [new Assert\Optional([new Assert\Length(max: 120)])],
        ]);
        $errors = $this->validator->validate($payload, $constraints);
        if (\count($errors) > 0) {
            return $this->violations($errors);
        }

        $email = strtolower(trim((string) $payload['email']));
        if ($this->users->findOneBy(['email' => $email]) !== null) {
            return new JsonResponse(['error' => 'Email already registered.'], 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($payload['display_name'] ?? null);
        $user->setRoles(['ROLE_CLIENT']);
        $user->setPassword($this->hasher->hashPassword($user, (string) $payload['password']));

        $this->em->persist($user);
        $this->em->flush();

        return new JsonResponse([
            'id'           => $user->getId(),
            'email'        => $user->getEmail(),
            'roles'        => $user->getRoles(),
            'display_name' => $user->getDisplayName(),
        ], 201);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return new JsonResponse(['error' => 'Unauthenticated.'], 401);
        }

        return new JsonResponse([
            'id'           => $user->getId(),
            'email'        => $user->getEmail(),
            'roles'        => $user->getRoles(),
            'display_name' => $user->getDisplayName(),
        ]);
    }

    /** @return array<string, mixed> */
    private function decode(Request $request): array
    {
        $body = (string) $request->getContent();
        if ($body === '') {
            return [];
        }
        try {
            $data = json_decode($body, true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($data) ? $data : [];
    }

    private function violations(\Symfony\Component\Validator\ConstraintViolationListInterface $errors): JsonResponse
    {
        $out = [];
        foreach ($errors as $e) {
            $out[] = ['property' => $e->getPropertyPath(), 'message' => $e->getMessage()];
        }

        return new JsonResponse(['error' => 'Validation failed.', 'violations' => $out], 422);
    }
}
