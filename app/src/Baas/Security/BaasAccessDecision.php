<?php

declare(strict_types=1);

namespace App\Baas\Security;

use App\Baas\Model\Model;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Small ABAC/RBAC gate for dynamic BaaS endpoints.
 *
 * Rules can be declared in model YAML under:
 *
 * api:
 *   abac:
 *     read:  { public: true }
 *     write: { roles: [ROLE_ADMIN] }
 *     delete:{ roles: [ROLE_ADMIN] }
 *
 * Defaults are intentionally safe for a BaaS demo: reads are public, mutations
 * require ROLE_ADMIN, and DDL/schema changes always require ROLE_ADMIN.
 */
final class BaasAccessDecision
{
    public function __construct(private readonly Security $security)
    {
    }

    public function denyUnlessAllowed(Model $model, string $action): void
    {
        if (!$this->isAllowed($model, $action)) {
            throw new AccessDeniedHttpException('BaaS policy denied this operation.');
        }
    }

    public function denyUnlessSchemaAdmin(): void
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException('Schema operations require ROLE_ADMIN.');
        }
    }

    public function isAllowed(Model $model, string $action): bool
    {
        $rule = $this->ruleFor($model, $action);

        if (($rule['public'] ?? false) === true) {
            return true;
        }

        $roles = $rule['roles'] ?? null;
        if (!\is_array($roles) || $roles === []) {
            $roles = \in_array($action, ['read', 'list'], true) ? ['PUBLIC_ACCESS'] : ['ROLE_ADMIN'];
        }

        foreach ($roles as $role) {
            if ($role === 'PUBLIC_ACCESS' || $this->security->isGranted((string) $role)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function ruleFor(Model $model, string $action): array
    {
        $abac = $model->api['abac'] ?? [];
        if (!\is_array($abac)) {
            return [];
        }

        $rule = $abac[$action] ?? null;
        if (\is_array($rule)) {
            return $rule;
        }

        return match ($action) {
            'list' => \is_array($abac['read'] ?? null) ? $abac['read'] : [],
            'create', 'update', 'patch' => \is_array($abac['write'] ?? null) ? $abac['write'] : [],
            default => [],
        };
    }
}
