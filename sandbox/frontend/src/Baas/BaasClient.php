<?php

declare(strict_types=1);

namespace App\Baas;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class BaasClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baasApiBase,
    ) {
    }

    /** @return array<string,mixed> */
    public function list(string $resource, array $query = []): array
    {
        return $this->request('GET', '/api/baas/' . rawurlencode($resource), ['query' => $query]);
    }

    /** @return array<string,mixed> */
    public function get(string $resource, int $id): array
    {
        return $this->request('GET', sprintf('/api/baas/%s/%d', rawurlencode($resource), $id));
    }

    /** @return array<string,mixed> */
    public function create(string $resource, array $payload, ?string $jwt = null): array
    {
        return $this->request('POST', '/api/baas/' . rawurlencode($resource), [
            'json' => $payload,
            'auth_bearer' => $jwt,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function allRows(string $resource, array $where = [], int $limit = 200): array
    {
        $response = $this->list($resource, ['where' => $where, 'limit' => $limit]);
        $data = $response['data'] ?? [];

        return \is_array($data) ? $data : [];
    }

    /** @return array<string,mixed> */
    private function request(string $method, string $path, array $options = []): array
    {
        if (($options['auth_bearer'] ?? null) === null) {
            unset($options['auth_bearer']);
        }

        $response = $this->httpClient->request($method, rtrim($this->baasApiBase, '/') . $path, $options);
        $status = $response->getStatusCode();
        $payload = $response->toArray(false);

        if ($status >= 400) {
            $message = $payload['error'] ?? $payload['message'] ?? 'BaaS request failed.';
            throw new BaasClientException((string) $message, $status);
        }

        return $payload;
    }
}
