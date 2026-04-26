<?php

declare(strict_types=1);

namespace App\Controller;

use App\Baas\Runtime\DynamicMongoGateway;
use App\Baas\Security\BaasAccessDecision;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/** Generic model-free CRUD controller for any MongoDB collection. */
final class MongoApiController extends AbstractController
{
    public function __construct(
        private readonly DynamicMongoGateway $mongo,
        private readonly BaasAccessDecision $access,
    ) {
    }

    #[Route('/api/mongo/resources', name: 'api_mongo_resources', methods: ['GET'], priority: 20)]
    public function resources(): JsonResponse
    {
        return new JsonResponse(['data' => $this->mongo->resources()]);
    }

    #[Route('/api/mongo/{resource}/schema', name: 'api_mongo_schema', methods: ['GET'], priority: 10)]
    public function schema(string $resource): JsonResponse
    {
        return new JsonResponse($this->mongo->schema($resource));
    }

    #[Route('/api/mongo/{resource}', name: 'api_mongo_collection', methods: ['GET', 'POST'], priority: -20)]
    public function collection(string $resource, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $this->access->denyUnlessAllowed('create');

            return new JsonResponse($this->mongo->create($resource, $this->decode($request)), 201);
        }

        $this->access->denyUnlessAllowed('list');

        return new JsonResponse($this->mongo->list($resource, $request->query->all()));
    }

    #[Route('/api/mongo/{resource}/{id}', name: 'api_mongo_item', methods: ['GET', 'PUT', 'PATCH', 'DELETE'], requirements: ['id' => '[^/]+'], priority: -20)]
    public function item(string $resource, string $id, Request $request): Response
    {
        if ($request->isMethod('GET')) {
            $this->access->denyUnlessAllowed('read');

            return new JsonResponse($this->mongo->get($resource, $id));
        }

        if ($request->isMethod('DELETE')) {
            $this->access->denyUnlessAllowed('delete');
            $this->mongo->delete($resource, $id);

            return new JsonResponse(null, 204);
        }

        $this->access->denyUnlessAllowed($request->isMethod('PATCH') ? 'patch' : 'update');

        return new JsonResponse($this->mongo->update($resource, $id, $this->decode($request)));
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
