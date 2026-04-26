<?php

declare(strict_types=1);

namespace App\Baas\Runtime;

use App\Baas\Security\InputSanitizer;
use MongoDB\Client;
use MongoDB\Collection;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Model-free MongoDB gateway driven by live collection names. */
final class DynamicMongoGateway
{
    private const DOCUMENT_NOT_FOUND = 'Document not found.';
    private const OBJECT_ID_CLASS = 'MongoDB\\BSON\\ObjectId';
    private const UTC_DATE_TIME_CLASS = 'MongoDB\\BSON\\UTCDateTime';

    public function __construct(
        private readonly Client $client,
        private readonly InputSanitizer $sanitizer,
        private readonly string $mongoDatabase,
    ) {
    }

    /** @return list<array<string,string>> */
    public function resources(): array
    {
        $resources = [];
        foreach ($this->client->selectDatabase($this->mongoDatabase)->listCollections() as $collection) {
            $name = $collection->getName();
            if (!str_starts_with($name, 'system.')) {
                $resources[] = ['resource' => $name, 'store' => 'mongo'];
            }
        }

        return $resources;
    }

    /** @return array{data:list<array<string,mixed>>,meta:array<string,int>} */
    public function list(string $resource, array $query): array
    {
        $collection = $this->collection($resource);
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(200, max(1, (int) ($query['limit'] ?? 30)));
        $where = \is_array($query['where'] ?? null) ? $this->sanitizeDocument($query['where']) : [];
        $sort = $this->sort((string) ($query['sort'] ?? ''));

        $cursor = $collection->find($where, [
            'limit' => $limit,
            'skip' => ($page - 1) * $limit,
            'sort' => $sort,
        ]);

        return [
            'data' => array_map(fn ($doc): array => $this->normalizeDocument((array) $doc), iterator_to_array($cursor, false)),
            'meta' => ['page' => $page, 'limit' => $limit, 'total' => $collection->countDocuments($where)],
        ];
    }

    /** @return array<string,mixed> */
    public function get(string $resource, string $id): array
    {
        $doc = $this->collection($resource)->findOne(['_id' => $this->id($id)]);
        if ($doc === null) {
            throw new NotFoundHttpException(self::DOCUMENT_NOT_FOUND);
        }

        return $this->normalizeDocument((array) $doc);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function create(string $resource, array $payload): array
    {
        $payload = $this->sanitizeDocument($payload);
        if ($payload === []) {
            throw new BadRequestHttpException('Empty payload.');
        }

        $result = $this->collection($resource)->insertOne($payload);

        return $this->get($resource, (string) $result->getInsertedId());
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function update(string $resource, string $id, array $payload): array
    {
        $payload = $this->sanitizeDocument($payload);
        unset($payload['_id'], $payload['id']);
        if ($payload === []) {
            throw new BadRequestHttpException('Empty payload.');
        }

        $result = $this->collection($resource)->updateOne(['_id' => $this->id($id)], ['$set' => $payload]);
        if ($result->getMatchedCount() === 0) {
            throw new NotFoundHttpException(self::DOCUMENT_NOT_FOUND);
        }

        return $this->get($resource, $id);
    }

    public function delete(string $resource, string $id): void
    {
        $result = $this->collection($resource)->deleteOne(['_id' => $this->id($id)]);
        if ($result->getDeletedCount() === 0) {
            throw new NotFoundHttpException(self::DOCUMENT_NOT_FOUND);
        }
    }

    /** @return array<string,mixed> */
    public function schema(string $resource): array
    {
        $collection = $this->collection($resource);
        $sample = $collection->findOne([], ['sort' => ['_id' => 1]]);

        return [
            'resource' => $resource,
            'store' => 'mongo',
            'sampleFields' => $sample === null ? [] : array_keys((array) $sample),
        ];
    }

    private function collection(string $resource): Collection
    {
        $resource = $this->sanitizer->assertIdentifier($resource, 'collection');

        return $this->client->selectCollection($this->mongoDatabase, $resource);
    }

    /** @return array<string,mixed> */
    private function sanitizeDocument(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            $key = (string) $key;
            if (str_starts_with($key, '$')) {
                throw new BadRequestHttpException('Mongo operator keys are not accepted in payloads.');
            }
            $clean[$this->sanitizer->assertIdentifier($key, 'field')] = $this->normalizeValue($this->sanitizer->sanitizeValue($value));
        }

        return $clean;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (\is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = $this->normalizeValue($item);
            }

            return $out;
        }
        if (\is_string($value) && str_starts_with($value, 'datetime:')) {
            $class = self::UTC_DATE_TIME_CLASS;

            return new $class((new \DateTimeImmutable(substr($value, 9)))->getTimestamp() * 1000);
        }

        return $value;
    }

    /** @return array<string,int> */
    private function sort(string $sort): array
    {
        if ($sort === '') {
            return ['_id' => 1];
        }
        $direction = str_starts_with($sort, '-') ? -1 : 1;
        $field = ltrim($sort, '-');
        $this->sanitizer->assertIdentifier($field, 'sort');

        return [$field => $direction];
    }

    private function id(string $id): mixed
    {
        if (!preg_match('/^[a-f0-9]{24}$/i', $id)) {
            return $id;
        }
        $class = self::OBJECT_ID_CLASS;

        return new $class($id);
    }

    /** @param array<string,mixed> $doc @return array<string,mixed> */
    private function normalizeDocument(array $doc): array
    {
        $out = [];
        foreach ($doc as $key => $value) {
            $publicKey = $key === '_id' ? 'id' : (string) $key;
            $out[$publicKey] = match (true) {
                is_a($value, self::OBJECT_ID_CLASS) => (string) $value,
                is_a($value, self::UTC_DATE_TIME_CLASS) => $value->toDateTime()->format(DATE_ATOM),
                \is_array($value) => $this->normalizeDocument($value),
                default => $value,
            };
        }

        return $out;
    }
}
