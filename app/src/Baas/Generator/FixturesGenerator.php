<?php

declare(strict_types=1);

namespace App\Baas\Generator;

use App\Baas\Model\Field;
use App\Baas\Model\Model;

/**
 * Emits Doctrine fixtures for postgres-backed models. The fixture class
 * persists `seeds.fixed` payloads verbatim and adds `seeds.count` random rows.
 *
 * For mongo-backed models, see the dedicated app:mongo:seed command which
 * reuses the same `seeds` block.
 */
final class FixturesGenerator
{
    public const MARKER = EntityGenerator::MARKER;

    public function generate(Model $model): string
    {
        $class = $model->name;
        $fqcn  = "App\\Entity\\{$class}";
        $count = (int) ($model->seeds['count'] ?? 0);
        /** @var list<array<string, mixed>> $fixed */
        $fixed = (array) ($model->seeds['fixed'] ?? []);
        $generators = (array) ($model->seeds['generators'] ?? []);

        $fixedRows = '';
        foreach ($fixed as $row) {
            $fixedRows .= $this->renderFixedRow($model, $row);
        }

        $randomBody = $this->renderRandomBody($model, $count, $generators);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\DataFixtures\\Generated;

use App\\Entity\\{$class};
use Doctrine\\Bundle\\FixturesBundle\\Fixture;
use Doctrine\\Persistence\\ObjectManager;

/**
 * @generated baas-codegen
 */
final class {$class}Fixtures extends Fixture
{
    public function load(ObjectManager \$manager): void
    {
{$fixedRows}{$randomBody}
        \$manager->flush();
    }
}

PHP;
    }

    private function renderFixedRow(Model $model, array $row): string
    {
        $class = $model->name;
        $var = '$' . lcfirst($class);
        $lines = "        {$var} = new {$class}();\n";
        foreach ($row as $field => $value) {
            $f = $model->fieldByName((string) $field);
            if ($f === null || $f->isPrimary()) {
                continue;
            }
            $cap = ucfirst((string) $field);
            $lines .= "        {$var}->set{$cap}(" . $this->literal($f, $value) . ");\n";
        }
        $lines .= "        \$manager->persist({$var});\n";

        return $lines;
    }

    private function renderRandomBody(Model $model, int $count, array $generators): string
    {
        if ($count <= 0) {
            return '';
        }
        $class = $model->name;
        $var = '$item';
        $body = "        for (\$i = 0; \$i < {$count}; \$i++) {\n";
        $body .= "            {$var} = new {$class}();\n";
        foreach ($model->fields as $f) {
            if ($f->isPrimary()) {
                continue;
            }
            $cap = ucfirst($f->name);
            $expr = $this->randomExpression($f, $generators[$f->name] ?? null);
            if ($expr === null) {
                continue;
            }
            $body .= "            {$var}->set{$cap}({$expr});\n";
        }
        $body .= "            \$manager->persist({$var});\n";
        $body .= "        }\n";

        return $body;
    }

    private function literal(Field $f, mixed $value): string
    {
        return match (true) {
            $f->isDateTimeType() && \is_string($value) => "new \\DateTimeImmutable(" . var_export($value, true) . ")",
            $f->type === 'json' && \is_array($value)   => var_export($value, true),
            default                                    => var_export($value, true),
        };
    }

    private function randomExpression(Field $f, mixed $generator): ?string
    {
        if ($generator !== null && \is_string($generator) && str_starts_with($generator, 'literal:')) {
            return substr($generator, 8);
        }

        return match ($f->type) {
            'int', 'smallint' => 'random_int(1, 1000)',
            'bigint'          => "(string) random_int(1, 1000000)",
            'float', 'decimal' => '(string) (random_int(100, 9999) / 100)',
            'bool'            => '(bool) random_int(0, 1)',
            'email'           => "'user' . random_int(1000,9999) . '@example.com'",
            'url'             => "'https://example.com/' . bin2hex(random_bytes(4))",
            'uuid'            => "bin2hex(random_bytes(16))",
            'string'          => "'sample-' . bin2hex(random_bytes(4))",
            'text'            => "'Lorem ipsum ' . bin2hex(random_bytes(8))",
            'json'            => '[]',
            'datetime_immutable', 'date', 'time' => "new \\DateTimeImmutable('+'.random_int(1,30).' days')",
            default => null,
        };
    }
}
