<?php

declare(strict_types=1);

namespace App\Baas\Model;

/**
 * Immutable description of one model field.
 */
final class Field
{
    /**
     * @param list<string>             $assert
     * @param list<string>             $groups
     * @param array<string, mixed>     $extra
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?int $length = null,
        public readonly bool $nullable = false,
        public readonly bool $unique = false,
        public readonly mixed $default = null,
        public readonly array $assert = [],
        public readonly array $groups = [],
        public readonly array $extra = [],
    ) {
    }

    public function isPrimary(): bool
    {
        return $this->type === 'id';
    }

    public function isDateTimeType(): bool
    {
        return \in_array($this->type, ['datetime_immutable', 'date', 'time'], true);
    }
}
