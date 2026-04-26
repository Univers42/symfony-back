<?php

declare(strict_types=1);

namespace App\Baas\Runtime;

use App\Baas\Security\InputSanitizer;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Model-free PostgreSQL gateway driven by live database introspection. */
final class DynamicDataGateway
{
    private const INTERNAL_TABLES = ['doctrine_migration_versions', 'messenger_messages', 'user'];

    public function __construct(
        private readonly Connection $connection,
        private readonly InputSanitizer $sanitizer,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function resources(): array
    {
        $tables = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT table_name AS resource
            FROM information_schema.tables
            WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
            ORDER BY table_name
        SQL);

        return array_values(array_filter(
            array_map(fn (array $row): array => ['resource' => $row['resource'], 'store' => 'postgres'], $tables),
            fn (array $row): bool => !$this->isInternalTable((string) $row['resource'])
        ));
    }

    /** @return array{data:list<array<string,mixed>>,meta:array<string,int>} */
    public function list(string $resource, array $query): array
    {
        $table = $this->table($resource);
        $columns = $this->columns($table);
        $primary = $this->primaryKey($table) ?? 'id';
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(200, max(1, (int) ($query['limit'] ?? 30)));
        $where = \is_array($query['where'] ?? null) ? $query['where'] : [];

        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->q($table))
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $count = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->q($table));

        $i = 0;
        foreach ($where as $field => $value) {
            $column = $this->columnForInput((string) $field, $columns);
            $param = 'w' . $i++;
            $value = $this->coerceValue($columns[$column], $value);
            $qb->andWhere($this->q($column) . ' = :' . $param)->setParameter($param, $value);
            $count->andWhere($this->q($column) . ' = :' . $param)->setParameter($param, $value);
        }

        [$sortColumn, $direction] = $this->sortColumn((string) ($query['sort'] ?? $primary), $columns);
        $qb->orderBy($this->q($sortColumn), $direction);

        return [
            'data' => array_map(fn (array $row): array => $this->normalizeRow($row), $qb->fetchAllAssociative()),
            'meta' => ['page' => $page, 'limit' => $limit, 'total' => (int) $count->fetchOne()],
        ];
    }

    /** @return array<string,mixed> */
    public function get(string $resource, string $id): array
    {
        $table = $this->table($resource);
        $primary = $this->primaryKey($table) ?? 'id';
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->q($table))
            ->where($this->q($primary) . ' = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        if ($row === false) {
            throw new NotFoundHttpException('Row not found.');
        }

        return $this->normalizeRow($row);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function create(string $resource, array $payload): array
    {
        $table = $this->table($resource);
        $columns = $this->columns($table);
        $values = $this->payloadToColumns($payload, $columns);
        if ($values === []) {
            throw new BadRequestHttpException('Empty payload.');
        }

        $this->connection->insert($table, $values);
        $primary = $this->primaryKey($table) ?? 'id';
        $id = (string) ($values[$primary] ?? $this->connection->lastInsertId());

        return $this->get($table, $id);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function update(string $resource, string $id, array $payload): array
    {
        $table = $this->table($resource);
        $columns = $this->columns($table);
        $primary = $this->primaryKey($table) ?? 'id';
        $values = $this->payloadToColumns($payload, $columns);
        unset($values[$primary]);
        if ($values === []) {
            throw new BadRequestHttpException('Empty payload.');
        }

        $affected = $this->connection->update($table, $values, [$primary => $id]);
        if ($affected === 0) {
            $this->get($table, $id);
        }

        return $this->get($table, $id);
    }

    public function delete(string $resource, string $id): void
    {
        $table = $this->table($resource);
        $primary = $this->primaryKey($table) ?? 'id';
        $affected = $this->connection->delete($table, [$primary => $id]);
        if ($affected === 0) {
            throw new NotFoundHttpException('Row not found.');
        }
    }

    /** @return array<string,mixed> */
    public function schema(string $resource): array
    {
        $table = $this->table($resource);

        return [
            'resource' => $table,
            'store' => 'postgres',
            'primaryKey' => $this->primaryKey($table),
            'columns' => array_values($this->columns($table)),
            'foreignKeys' => $this->foreignKeys($table),
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private function columns(string $table): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = :table
            ORDER BY ordinal_position
        SQL, ['table' => $table]);

        if ($rows === []) {
            throw new NotFoundHttpException(sprintf('Unknown PostgreSQL resource "%s".', $table));
        }

        $columns = [];
        foreach ($rows as $row) {
            $name = (string) $row['column_name'];
            $columns[$name] = [
                'name' => $name,
                'publicName' => DynamicName::toCamel($name),
                'type' => (string) $row['data_type'],
                'nullable' => $row['is_nullable'] === 'YES',
                'default' => $row['column_default'],
            ];
        }

        return $columns;
    }

    private function primaryKey(string $table): ?string
    {
        $key = $this->connection->fetchOne(<<<'SQL'
            SELECT kcu.column_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
              ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
            WHERE tc.constraint_type = 'PRIMARY KEY'
              AND tc.table_schema = 'public'
              AND tc.table_name = :table
            ORDER BY kcu.ordinal_position
            LIMIT 1
        SQL, ['table' => $table]);

        return \is_string($key) ? $key : null;
    }

    /** @return list<array<string,mixed>> */
    private function foreignKeys(string $table): array
    {
        return $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT kcu.column_name AS column, ccu.table_name AS referenced_table, ccu.column_name AS referenced_column
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
              ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage ccu
              ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_schema = 'public'
              AND tc.table_name = :table
            ORDER BY kcu.column_name
        SQL, ['table' => $table]);
    }

    /** @param array<string,mixed> $payload @param array<string,array<string,mixed>> $columns @return array<string,mixed> */
    private function payloadToColumns(array $payload, array $columns): array
    {
        $payload = $this->sanitizer->sanitizePayload($payload);
        $values = [];
        foreach ($payload as $field => $value) {
            $column = $this->columnForInput($field, $columns);
            $values[$column] = $this->coerceValue($columns[$column], $value);
        }

        return $values;
    }

    /** @param array<string,mixed> $column */
    private function coerceValue(array $column, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        $value = $this->sanitizer->sanitizeValue($value);
        $type = (string) $column['type'];

        return match ($type) {
            'smallint', 'integer', 'bigint' => (int) $value,
            'real', 'double precision', 'numeric', 'decimal' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            'json', 'jsonb' => \is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value,
            'timestamp without time zone', 'timestamp with time zone' => (new \DateTimeImmutable((string) $value))->format('Y-m-d H:i:s'),
            'date' => (new \DateTimeImmutable((string) $value))->format('Y-m-d'),
            'time without time zone', 'time with time zone' => (new \DateTimeImmutable((string) $value))->format('H:i:s'),
            default => (string) $value,
        };
    }

    /** @param array<string,array<string,mixed>> $columns */
    private function columnForInput(string $field, array $columns): string
    {
        $field = $this->sanitizer->assertIdentifier($field, 'field');
        if (isset($columns[$field])) {
            return $field;
        }
        $snake = DynamicName::toSnake($field);
        if (isset($columns[$snake])) {
            return $snake;
        }

        throw new BadRequestHttpException(sprintf('Unknown field "%s".', $field));
    }

    /** @param array<string,array<string,mixed>> $columns @return array{0:string,1:string} */
    private function sortColumn(string $sort, array $columns): array
    {
        $direction = str_starts_with($sort, '-') ? 'DESC' : 'ASC';
        $field = ltrim($sort, '-');
        if ($field === '') {
            $field = array_key_first($columns) ?: 'id';
        }

        return [$this->columnForInput($field, $columns), $direction];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeRow(array $row): array
    {
        $out = [];
        foreach ($row as $column => $value) {
            $out[DynamicName::toCamel((string) $column)] = $value;
        }

        return $out;
    }

    private function table(string $resource): string
    {
        $table = strtolower($this->sanitizer->assertIdentifier($resource, 'resource'));
        if ($this->isInternalTable($table)) {
            throw new NotFoundHttpException(sprintf('Unknown PostgreSQL resource "%s".', $resource));
        }
        $this->columns($table);

        return $table;
    }

    private function isInternalTable(string $table): bool
    {
        return \in_array($table, self::INTERNAL_TABLES, true) || str_starts_with($table, 'ea_');
    }

    private function q(string $identifier): string
    {
        return $this->connection->quoteIdentifier($this->sanitizer->assertIdentifier($identifier));
    }
}
