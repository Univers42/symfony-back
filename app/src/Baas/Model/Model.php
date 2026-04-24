<?php

declare(strict_types=1);

namespace App\Baas\Model;

/**
 * Immutable description of one model (resource).
 */
final class Model
{
    /**
     * @param list<Field>                  $fields
     * @param list<Relation>               $relations
     * @param list<array<string, mixed>>   $indexes
     * @param array<string, mixed>         $api
     * @param array<string, mixed>         $seeds
     */
    public function __construct(
        public readonly string $name,
        public readonly string $table,
        public readonly string $store,        // postgres | mongo
        public readonly array $fields,
        public readonly array $relations = [],
        public readonly array $indexes = [],
        public readonly array $api = [],
        public readonly array $seeds = [],
        public readonly ?string $description = null,
        public readonly bool $trackChanges = false,
        public readonly string $sourcePath = '',
    ) {
    }

    public function isPostgres(): bool
    {
        return $this->store === 'postgres';
    }

    public function isMongo(): bool
    {
        return $this->store === 'mongo';
    }

    public function snake(): string
    {
        return self::toSnake($this->name);
    }

    public function pluralSnake(): string
    {
        return $this->table;
    }

    public function fieldByName(string $name): ?Field
    {
        foreach ($this->fields as $f) {
            if ($f->name === $name) {
                return $f;
            }
        }

        return null;
    }

    public static function toSnake(string $pascal): string
    {
        return strtolower((string) preg_replace('/(?<!^)([A-Z])/', '_$1', $pascal));
    }
}
