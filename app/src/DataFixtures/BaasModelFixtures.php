<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Baas\Loader\ModelLoader;
use App\Baas\Model\Field;
use App\Baas\Model\Model;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;

/** Loads demo/application data from sandbox model YAML, not from product code. */
final class BaasModelFixtures extends Fixture
{
    public function __construct(
        private readonly ModelLoader $loader,
        private readonly Connection $connection,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $models = array_values(array_filter($this->loader->loadAll(), fn (Model $model): bool => $model->isPostgres()));
        foreach ($this->sortByRelations($models) as $model) {
            $fixed = $model->seeds['fixed'] ?? [];
            if (!\is_array($fixed) || $fixed === []) {
                continue;
            }

            foreach ($fixed as $row) {
                if (!\is_array($row)) {
                    continue;
                }
                $this->connection->insert($model->table, $this->rowToColumns($model, $row));
            }
            $this->resetIdentity($model->table);
        }
    }

    /** @param list<Model> $models @return list<Model> */
    private function sortByRelations(array $models): array
    {
        $byName = [];
        foreach ($models as $model) {
            $byName[$model->name] = $model;
        }

        $sorted = [];
        $visiting = [];
        $visited = [];

        $visit = function (Model $model) use (&$visit, &$sorted, &$visiting, &$visited, $byName): void {
            if (isset($visited[$model->name])) {
                return;
            }
            if (isset($visiting[$model->name])) {
                return;
            }
            $visiting[$model->name] = true;
            foreach ($model->relations as $relation) {
                if (isset($byName[$relation->target])) {
                    $visit($byName[$relation->target]);
                }
            }
            $visited[$model->name] = true;
            $sorted[] = $model;
        };

        foreach ($models as $model) {
            $visit($model);
        }

        return $sorted;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function rowToColumns(Model $model, array $row): array
    {
        $columns = [];
        foreach ($model->fields as $field) {
            $column = $field->isPrimary() ? 'id' : Model::toSnake($field->name);
            if (array_key_exists($field->name, $row)) {
                $columns[$column] = $this->value($field, $row[$field->name]);
            } elseif (array_key_exists($column, $row)) {
                $columns[$column] = $this->value($field, $row[$column]);
            } elseif ($field->default !== null && !$field->isPrimary()) {
                $columns[$column] = $this->defaultValue($field);
            }
        }

        foreach ($model->relations as $relation) {
            $column = Model::toSnake($relation->name) . '_id';
            $camelId = $relation->name . 'Id';
            if (array_key_exists($camelId, $row)) {
                $columns[$column] = (int) $row[$camelId];
            } elseif (array_key_exists($column, $row)) {
                $columns[$column] = (int) $row[$column];
            }
        }

        return $columns;
    }

    private function value(Field $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field->type) {
            'datetime_immutable' => (new \DateTimeImmutable((string) $value))->format('Y-m-d H:i:s'),
            'date' => (new \DateTimeImmutable((string) $value))->format('Y-m-d'),
            'time' => (new \DateTimeImmutable((string) $value))->format('H:i:s'),
            'json' => \is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value,
            default => $value,
        };
    }

    private function defaultValue(Field $field): mixed
    {
        return match (true) {
            $field->default === 'now' && $field->isDateTimeType() => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            default => $field->default,
        };
    }

    private function resetIdentity(string $table): void
    {
        $sequence = $this->connection->fetchOne('SELECT pg_get_serial_sequence(:table, :column)', ['table' => $table, 'column' => 'id']);
        if (\is_string($sequence) && $sequence !== '') {
            $this->connection->executeStatement(sprintf("SELECT setval('%s', COALESCE((SELECT MAX(id) FROM %s), 1), true)", str_replace("'", "''", $sequence), $this->connection->quoteIdentifier($table)));
        }
    }
}
