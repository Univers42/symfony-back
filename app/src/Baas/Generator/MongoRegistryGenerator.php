<?php

declare(strict_types=1);

namespace App\Baas\Generator;

use App\Baas\Model\Model;

/**
 * Emits a static registry mapping mongo resource slugs (snake-cased plural,
 * matching `table`) to fully-qualified document class names. Consumed by
 * {@see App\Controller\MongoApiController}.
 */
final class MongoRegistryGenerator
{
    public const MARKER = EntityGenerator::MARKER;

    /** @param list<Model> $models */
    public function generate(array $models): string
    {
        $entries = [];
        foreach ($models as $m) {
            if (!$m->isMongo()) {
                continue;
            }
            $entries[] = "        '{$m->table}' => \\App\\Document\\{$m->name}::class,";
        }
        $body = $entries === [] ? '' : implode("\n", $entries);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Baas\\Generated;

/**
 * Compile-time mapping of mongo resource slug -> document class.
 *
 * @generated baas-codegen
 */
final class MongoResourceRegistry
{
    /** @return array<string, class-string> */
    public static function all(): array
    {
        return [
{$body}
        ];
    }

    public static function classFor(string \$slug): ?string
    {
        return self::all()[\$slug] ?? null;
    }
}

PHP;
    }
}
