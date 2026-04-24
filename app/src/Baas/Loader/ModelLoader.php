<?php

declare(strict_types=1);

namespace App\Baas\Loader;

use App\Baas\Model\Field;
use App\Baas\Model\Model;
use App\Baas\Model\Relation;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Walks /models recursively and produces a list of Model objects.
 *
 * Files starting with "_" (e.g. _schema.yaml) are skipped intentionally — they
 * are reserved for documentation, fragments and overrides.
 */
final class ModelLoader
{
    public function __construct(private readonly string $modelsDir)
    {
    }

    /** @return list<Model> */
    public function loadAll(): array
    {
        if (!is_dir($this->modelsDir)) {
            return [];
        }

        $finder = (new Finder())
            ->files()
            ->in($this->modelsDir)
            ->name('*.yaml')
            ->name('*.yml')
            ->notName('_*')
            ->sortByName();

        $models = [];
        foreach ($finder as $file) {
            $raw = Yaml::parseFile($file->getRealPath()) ?? [];
            if (!\is_array($raw) || !isset($raw['name'])) {
                throw new \RuntimeException(\sprintf(
                    'Invalid model file "%s": missing "name" key.',
                    $file->getRelativePathname(),
                ));
            }
            $models[] = $this->hydrate($raw, $file->getRealPath());
        }

        return $models;
    }

    /** @param array<string, mixed> $raw */
    private function hydrate(array $raw, string $path): Model
    {
        $store = $raw['store'] ?? 'postgres';
        $name  = (string) $raw['name'];

        $fields = [];
        foreach ((array) ($raw['fields'] ?? []) as $rawField) {
            $fields[] = new Field(
                name:     (string) $rawField['name'],
                type:     (string) $rawField['type'],
                length:   isset($rawField['length']) ? (int) $rawField['length'] : null,
                nullable: (bool)  ($rawField['nullable'] ?? false),
                unique:   (bool)  ($rawField['unique'] ?? false),
                default:  $rawField['default'] ?? null,
                assert:   array_values((array) ($rawField['assert'] ?? [])),
                groups:   array_values((array) ($rawField['groups'] ?? [])),
                extra:    \is_array($rawField['extra'] ?? null) ? $rawField['extra'] : [],
            );
        }

        $relations = [];
        foreach ((array) ($raw['relations'] ?? []) as $rawRel) {
            $relations[] = new Relation(
                name:       (string) $rawRel['name'],
                type:       (string) $rawRel['type'],
                target:     (string) $rawRel['target'],
                nullable:   (bool)  ($rawRel['nullable'] ?? false),
                mappedBy:   $rawRel['mapped_by']   ?? null,
                inversedBy: $rawRel['inversed_by'] ?? null,
                cascade:    array_values((array) ($rawRel['cascade'] ?? [])),
                onDelete:   $rawRel['on_delete'] ?? null,
                orderBy:    \is_array($rawRel['order_by'] ?? null) ? $rawRel['order_by'] : null,
            );
        }

        return new Model(
            name:         $name,
            table:        (string) ($raw['table'] ?? Model::toSnake($name) . 's'),
            store:        $store,
            fields:       $fields,
            relations:    $relations,
            indexes:      array_values((array) ($raw['indexes'] ?? [])),
            api:          (array)        ($raw['api'] ?? []),
            seeds:        (array)        ($raw['seeds'] ?? []),
            description:  $raw['description'] ?? null,
            trackChanges: (bool)         ($raw['track_changes'] ?? false),
            sourcePath:   $path,
        );
    }
}
