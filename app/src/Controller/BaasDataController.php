<?php

declare(strict_types=1);

namespace App\Controller;

use App\Baas\Runtime\DynamicDataGateway;
use App\Baas\Runtime\ModelRegistry;
use App\Baas\Security\BaasAccessDecision;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/** Generic model-agnostic PostgreSQL CRUD API for frontend/sandbox apps. */
final class BaasDataController extends AbstractController
{
    public function __construct(
        private readonly ModelRegistry $models,
        private readonly DynamicDataGateway $gateway,
        private readonly BaasAccessDecision $access,
    ) {
    }

    #[Route('/api/baas/resources', name: 'api_baas_resources', methods: ['GET'], priority: 20)]
    public function resources(): JsonResponse
    {
        return new JsonResponse([
            'data' => array_map(fn ($model): array => $this->gateway->schema($model), $this->models->all()),
        ]);
    }

    #[Route('/api/baas/{resource}', name: 'api_baas_collection', methods: ['GET', 'POST'], priority: -20)]
    public function collection(string $resource, Request $request): Response
    {
        $model = $this->models->postgresResource($resource);

        if ($request->isMethod('POST')) {
            $this->access->denyUnlessAllowed($model, 'create');

            return new JsonResponse($this->gateway->create($model, $this->decode($request)), 201);
        }

        $this->access->denyUnlessAllowed($model, 'list');

        return new JsonResponse($this->gateway->list($model, $request->query->all()));
    }

    #[Route('/api/baas/{resource}/{id}', name: 'api_baas_item', methods: ['GET', 'PUT', 'PATCH', 'DELETE'], requirements: ['id' => '\\d+'], priority: -20)]
    public function item(string $resource, int $id, Request $request): Response
    {
        $model = $this->models->postgresResource($resource);

        if ($request->isMethod('GET')) {
            $this->access->denyUnlessAllowed($model, 'read');

            return new JsonResponse($this->gateway->get($model, $id));
        }

        if ($request->isMethod('DELETE')) {
            $this->access->denyUnlessAllowed($model, 'delete');
            $this->gateway->delete($model, $id);

            return new JsonResponse(null, 204);
        }

        $this->access->denyUnlessAllowed($model, $request->isMethod('PATCH') ? 'patch' : 'update');

        return new JsonResponse($this->gateway->update($model, $id, $this->decode($request)));
    }

    /** @return array<string,mixed> */
    private function decode(Request $request): array
    {
        $body = trim((string) $request->getContent());
        if ($body === '') {
            return [];
        }

        try {
            $payload = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BadRequestHttpException('Invalid JSON body: ' . $e->getMessage());
        }

        if (!\is_array($payload)) {
            throw new BadRequestHttpException('JSON body must be an object.');
        }

        return $payload;
    }
}
