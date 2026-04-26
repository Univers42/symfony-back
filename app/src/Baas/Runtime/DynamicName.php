<?php

declare(strict_types=1);

namespace App\Baas\Runtime;

final class DynamicName
{
    public static function toCamel(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }

    public static function toSnake(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}
