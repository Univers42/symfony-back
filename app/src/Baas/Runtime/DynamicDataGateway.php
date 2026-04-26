<?php

declare(strict_types=1);

namespace App\Baas\Runtime;

use App\Baas\Model\Field;
use App\Baas\Model\Model;
use App\Baas\Security\InputSanitizer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DynamicDataGateway
{
    public function __construct(
        private readonly Connection $connection,
        private readonly InputSanitizer $sanitizer,
    ) {
    }

    /** @return array{data:list<array<string,mixed>>,meta:array<string,int>} */
    public function list(Model $model, array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(200, max(1, (int) ($query['limit'] ?? 30)));
        $where = \is_array($query['where'] ?? null) ? $query['where'] : [];
        $sort = (string) ($query['sort'] ?? 'id');

        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->quoteIdentifier($model->table))
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $count = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->quoteIdentifier($model->table));

        $i = 0;
        foreach ($where as $field => $value) {
            $column = $this->columnForInput($model, (string) $field);
            $param = 'w' . $i++;
            $qb->andWhere($this->quoteIdentifier($column) . ' = :' . $param)->setParameter($param, $this->coerceColumnValue($model, $column, $value));
            $count->andWhere($this->quoteIdentifier($column) . ' = :' . $param)->setParameter($param, $this->coerceColumnValue($model, $column, $value));
        }

        [$sortColumn, $direction] = $this->sortColumn($model, $sort);
        $qb->orderBy($this->quoteIdentifier($sortColumn), $direction);

        return [
            'data' => array_map(fn (array $row): array => $this->normalizeRow($model, $row), $qb->fetchAllAssociative()),
            'meta' => ['page' => $page, 'limit' => $limit, 'total' => (int) $count->fetchOne()],
        ];
    }

    /** @return array<string,mixed> */
    public function get(Model $model, int $id): array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->quoteIdentifier($model->table))
            ->where('id = :id')
            ->setParameter('id', $id, ParameterType::INTEGER)
            ->fetchAssociative();

        if ($row === false) {
            throw new NotFoundHttpException('Row not found.');
        }

        return $this->normalizeRow($model, $row);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function create(Model $model, array $payload): array
    {
        $values = $this->payloadToColumns($model, $payload, includeDefaults: true);
        if ($values === []) {
            throw new BadRequestHttpException('Empty payload.');
        }

        $this->connection->insert($model->table, $values);
        $id = (int) $this->connection->lastInsertId();

        return $this->get($model, $id);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function update(Model $model, int $id, array $payload): array
    {
        $values = $this->payloadToColumns($model, $payload, includeDefaults: false);
        if ($values === []) {
            throw new BadRequestHttpException('Empty payload.');
        }

        $affected = $this->connection->update($model->table, $values, ['id' => $id]);
        if ($affected === 0) {
            $this->get($model, $id);
        }

        return $this->get($model, $id);
    }

    public function delete(Model $model, int $id): void
    {
        $affected = $this->connection->delete($model->table, ['id' => $id]);
        if ($affected === 0) {
            throw new NotFoundHttpException('Row not found.');
        }
    }

    /** @return array<string,mixed> */
    public function schema(Model $model): array
    {
        return [
            'name' => $model->name,
            'resource' => $model->table,
            'store' => $model->store,
            'fields' => array_map(fn (Field $field): array => [
                'name' => $field->name,
                'column' => $field->isPrimary() ? 'id' : Model::toSnake($field->name),
                'type' => $field->type,
                'nullable' => $field->nullable,
                'unique' => $field->unique,
                'default' => $field->default,
            ], $model->fields),
            'relations' => array_map(fn ($relation): array => [
                'name' => $relation->name,
                'column' => Model::toSnake($relation->name) . '_id',
                'type' => $relation->type,
                'target' => $relation->target,
                'nullable' => $relation->nullable,
            ], $model->relations),
            'api' => $model->api,
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function payloadToColumns(Model $model, array $payload, bool $includeDefaults): array
    {
        $payload = $this->sanitizer->sanitizePayload($payload);
        $values = [];

        foreach ($model->fields as $field) {
            if ($field->isPrimary()) {
                continue;
            }
            $column = Model::toSnake($field->name);
            if (array_key_exists($field->name, $payload)) {
                $values[$column] = $this->coerceFieldValue($field, $payload[$field->name]);
            } elseif (array_key_exists($column, $payload)) {
                $values[$column] = $this->coerceFieldValue($field, $payload[$column]);
            } elseif ($includeDefaults && $field->default !== null) {
                $values[$column] = $this->defaultValue($field);
            }
        }

        foreach ($model->relations as $relation) {
            $column = Model::toSnake($relation->name) . '_id';
            $camelId = $relation->name . 'Id';
            if (array_key_exists($camelId, $payload)) {
                $values[$column] = (int) $payload[$camelId];
            } elseif (array_key_exists($column, $payload)) {
                $values[$column] = (int) $payload[$column];
            }
        }

        return $values;
    }

    private function defaultValue(Field $field): mixed
    {
        return match (true) {
            $field->default === 'now' && $field->isDateTimeType() => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $field->default === 'uuid' => bin2hex(random_bytes(16)),
            default => $field->default,
        };
    }

    private function coerceColumnValue(Model $model, string $column, mixed $value): mixed
    {
        foreach ($model->fields as $field) {
            if (Model::toSnake($field->name) === $column || $field->name === $column) {
                return $this->coerceFieldValue($field, $value);
            }
        }

        return $this->sanitizer->sanitizeValue($value);
    }

    private function coerceFieldValue(Field $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        $value = $this->sanitizer->sanitizeValue($value);

        return match ($field->type) {
            'int', 'smallint' => (int) $value,
            'bigint', 'decimal', 'uuid', 'email', 'url', 'string', 'text' => (string) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            'json' => \is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value,
            'datetime_immutable' => (new \DateTimeImmutable((string) $value))->format('Y-m-d H:i:s'),
            'date' => (new \DateTimeImmutable((string) $value))->format('Y-m-d'),
            'time' => (new \DateTimeImmutable((string) $value))->format('H:i:s'),
            default => $value,
        };
    }

    private function columnForInput(Model $model, string $field): string
    {
        $field = $this->sanitizer->assertIdentifier($field, 'field');
        if ($field === 'id') {
            return 'id';
        }
        foreach ($model->fields as $modelField) {
            if ($modelField->name === $field || Model::toSnake($modelField->name) === $field) {
                return Model::toSnake($modelField->name);
            }
        }
        foreach ($model->relations as $relation) {
            $column = Model::toSnake($relation->name) . '_id';
            if ($field === $column || $field === $relation->name . 'Id') {
                return $column;
            }
        }

        throw new BadRequestHttpException(sprintf('Unknown field "%s".', $field));
    }

    /** @return array{0:string,1:string} */
    private function sortColumn(Model $model, string $sort): array
    {
        $direction = str_starts_with($sort, '-') ? 'DESC' : 'ASC';
        $field = ltrim($sort, '-');
        if ($field === '') {
            $field = 'id';
        }

        return [$this->columnForInput($model, $field), $direction];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeRow(Model $model, array $row): array
    {
        $out = [];
        foreach ($row as $column => $value) {
            $out[$this->publicName($model, (string) $column)] = $value;
        }

        return $out;
    }

    private function publicName(Model $model, string $column): string
    {
        foreach ($model->fields as $field) {
            if (Model::toSnake($field->name) === $column) {
                return $field->name;
            }
        }
        foreach ($model->relations as $relation) {
            if (Model::toSnake($relation->name) . '_id' === $column) {
                return $relation->name . 'Id';
            }
        }

        return $column;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return $this->connection->quoteIdentifier($this->sanitizer->assertIdentifier($identifier));
    }
}
