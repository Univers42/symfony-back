<?php

declare(strict_types=1);

namespace App\Baas\Security;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Defensive input normalization for the generic BaaS layer.
 * It rejects suspicious identifiers and strips active HTML from strings before
 * values are persisted or used in SQL builders.
 */
final class InputSanitizer
{
    private const IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]{0,62}$/';

    public function assertIdentifier(string $identifier, string $label = 'identifier'): string
    {
        if (!preg_match(self::IDENTIFIER_PATTERN, $identifier)) {
            throw new BadRequestHttpException(sprintf('Invalid %s "%s".', $label, $identifier));
        }

        return $identifier;
    }

    public function sanitizeValue(mixed $value): mixed
    {
        if (\is_string($value)) {
            return $this->sanitizeString($value);
        }

        if (\is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $clean[$key] = $this->sanitizeValue($item);
            }

            return $clean;
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    public function sanitizePayload(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            $this->assertIdentifier((string) $key, 'field');
            $clean[(string) $key] = $this->sanitizeValue($value);
        }

        return $clean;
    }

    private function sanitizeString(string $value): string
    {
        // Remove NUL bytes and control chars except common whitespace.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        // Persist text, not executable markup. Twig/JSON responses still escape
        // output; this is an additional storage-layer guard against XSS payloads.
        return trim(strip_tags($value));
    }
}
