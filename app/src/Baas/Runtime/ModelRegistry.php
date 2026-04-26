<?php

declare(strict_types=1);

namespace App\Baas\Runtime;

use App\Baas\Loader\ModelLoader;
use App\Baas\Loader\ModelValidator;
use App\Baas\Model\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ModelRegistry
{
    /** @var array<string, Model>|null */
    private ?array $byResource = null;

    public function __construct(
        private readonly ModelLoader $loader,
        private readonly ModelValidator $validator,
    ) {
    }

    /** @return list<Model> */
    public function all(): array
    {
        $this->boot();

        return array_values($this->byResource ?? []);
    }

    public function postgresResource(string $resource): Model
    {
        $model = $this->resource($resource);
        if (!$model->isPostgres()) {
            throw new NotFoundHttpException(sprintf('Resource "%s" is not a PostgreSQL resource.', $resource));
        }

        return $model;
    }

    public function resource(string $resource): Model
    {
        $this->boot();
        $key = strtolower($resource);
        $model = $this->byResource[$key] ?? null;
        if ($model === null) {
            throw new NotFoundHttpException(sprintf('Unknown BaaS resource "%s".', $resource));
        }

        return $model;
    }

    private function boot(): void
    {
        if ($this->byResource !== null) {
            return;
        }

        $models = $this->loader->loadAll();
        $errors = $this->validator->validate($models);
        if ($errors !== []) {
            throw new InvalidModelConfigurationException('Invalid BaaS model configuration: ' . implode(' ', $errors));
        }

        $this->byResource = [];
        foreach ($models as $model) {
            $this->byResource[strtolower($model->table)] = $model;
            $this->byResource[strtolower($model->snake())] = $model;
        }
    }
}
