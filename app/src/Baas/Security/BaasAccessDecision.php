<?php

declare(strict_types=1);

namespace App\Baas\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Small ABAC/RBAC gate for dynamic BaaS endpoints.
 *
 * Defaults are intentionally safe: reads are public, mutations require
 * ROLE_ADMIN, and DDL/schema changes always require ROLE_ADMIN.
 */
final class BaasAccessDecision
{
    public function __construct(private readonly Security $security)
    {
    }

    public function denyUnlessAllowed(string $action): void
    {
        if (!$this->isAllowed($action)) {
            throw new AccessDeniedHttpException('BaaS policy denied this operation.');
        }
    }

    public function denyUnlessSchemaAdmin(): void
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException('Schema operations require ROLE_ADMIN.');
        }
    }

    public function isAllowed(string $action): bool
    {
        if (\in_array($action, ['read', 'list', 'schema'], true)) {
            return true;
        }

        return $this->security->isGranted('ROLE_ADMIN');
    }
}
