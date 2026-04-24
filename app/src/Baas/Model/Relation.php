<?php

declare(strict_types=1);

namespace App\Baas\Model;

/**
 * Immutable description of one relational association (postgres only).
 */
final class Relation
{
    /** @param list<string> $cascade */
    public function __construct(
        public readonly string $name,
        public readonly string $type,        // many_to_one | one_to_many | many_to_many | one_to_one
        public readonly string $target,      // PascalCase model name
        public readonly bool $nullable = false,
        public readonly ?string $mappedBy = null,
        public readonly ?string $inversedBy = null,
        public readonly array $cascade = [],
        public readonly ?string $onDelete = null,
        /** @var array<string, string>|null */
        public readonly ?array $orderBy = null,
    ) {
    }

    public function isOwning(): bool
    {
        return $this->mappedBy === null && \in_array($this->type, ['many_to_one', 'one_to_one'], true);
    }
}
