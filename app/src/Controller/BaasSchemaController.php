<?php

declare(strict_types=1);

namespace App\Controller;

use App\Baas\Runtime\ModelRegistry;
use App\Baas\Runtime\SchemaOperationGateway;
use App\Baas\Security\BaasAccessDecision;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/** Admin-only guarded DDL API. */
#[Route('/api/baas/schema', priority: 30)]
final class BaasSchemaController extends AbstractController
{
    public function __construct(
        private readonly ModelRegistry $models,
        private readonly SchemaOperationGateway $schema,
        private readonly BaasAccessDecision $access,
    ) {
    }

    #[Route('', name: 'api_baas_schema', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse([
            'models' => array_map(fn ($model) => [
                'name' => $model->name,
                'resource' => $model->table,
                'store' => $model->store,
            ], $this->models->all()),
            'tables' => $this->schema->tables(),
        ]);
    }

    #[Route('/tables/{table}/columns', name: 'api_baas_schema_columns', methods: ['GET'])]
    public function columns(string $table): JsonResponse
    {
        return new JsonResponse(['data' => $this->schema->columns($table)]);
    }

    #[Route('/tables', name: 'api_baas_schema_table_create', methods: ['POST'])]
    public function createTable(Request $request): JsonResponse
    {
        $this->access->denyUnlessSchemaAdmin();
        $payload = $this->decode($request);
        $this->schema->createTable((string) ($payload['name'] ?? ''), \is_array($payload['columns'] ?? null) ? $payload['columns'] : []);

        return new JsonResponse(['status' => 'created'], 201);
    }

    #[Route('/tables/{table}', name: 'api_baas_schema_table_update', methods: ['PATCH'])]
    public function renameTable(string $table, Request $request): JsonResponse
    {
        $this->access->denyUnlessSchemaAdmin();
        $payload = $this->decode($request);
        $this->schema->renameTable($table, (string) ($payload['name'] ?? ''));

        return new JsonResponse(['status' => 'renamed']);
    }

    #[Route('/tables/{table}', name: 'api_baas_schema_table_delete', methods: ['DELETE'])]
    public function dropTable(string $table): JsonResponse
    {
        $this->access->denyUnlessSchemaAdmin();
        $this->schema->dropTable($table);

        return new JsonResponse(null, 204);
    }

    #[Route('/tables/{table}/columns', name: 'api_baas_schema_column_create', methods: ['POST'])]
    public function addColumn(string $table, Request $request): JsonResponse
    {
        $this->access->denyUnlessSchemaAdmin();
        $this->schema->addColumn($table, $this->decode($request));

        return new JsonResponse(['status' => 'created'], 201);
    }

    #[Route('/tables/{table}/columns/{column}', name: 'api_baas_schema_column_update', methods: ['PATCH'])]
    public function alterColumn(string $table, string $column, Request $request): JsonResponse
    {
        $this->access->denyUnlessSchemaAdmin();
        $this->schema->alterColumn($table, $column, $this->decode($request));

        return new JsonResponse(['status' => 'updated']);
    }

    #[Route('/tables/{table}/columns/{column}', name: 'api_baas_schema_column_delete', methods: ['DELETE'])]
    public function dropColumn(string $table, string $column): JsonResponse
    {
        $this->access->denyUnlessSchemaAdmin();
        $this->schema->dropColumn($table, $column);

        return new JsonResponse(null, 204);
    }

    /** @return array<string,mixed> */
    private function decode(Request $request): array
    {
        try {
            $payload = json_decode((string) $request->getContent(), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BadRequestHttpException('Invalid JSON body: ' . $e->getMessage());
        }

        if (!\is_array($payload)) {
            throw new BadRequestHttpException('JSON body must be an object.');
        }

        return $payload;
    }
}
