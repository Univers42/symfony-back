<?php

declare(strict_types=1);

namespace App\Baas\Generator;

use App\Baas\Model\Field;
use App\Baas\Model\Model;

/**
 * Emits a Doctrine MongoDB ODM document class for a Mongo-backed model.
 * Output file is marked "@generated baas-codegen".
 */
final class DocumentGenerator
{
    public const MARKER = EntityGenerator::MARKER;

    private const TYPE_MAP = [
        'int'                => ['php' => 'int',                'odm' => 'int'],
        'bigint'             => ['php' => 'string',             'odm' => 'string'],
        'smallint'           => ['php' => 'int',                'odm' => 'int'],
        'float'              => ['php' => 'float',              'odm' => 'float'],
        'decimal'            => ['php' => 'string',             'odm' => 'decimal128'],
        'bool'               => ['php' => 'bool',               'odm' => 'bool'],
        'string'             => ['php' => 'string',             'odm' => 'string'],
        'email'              => ['php' => 'string',             'odm' => 'string'],
        'url'                => ['php' => 'string',             'odm' => 'string'],
        'text'               => ['php' => 'string',             'odm' => 'string'],
        'json'               => ['php' => 'array',              'odm' => 'hash'],
        'datetime_immutable' => ['php' => '\DateTimeImmutable', 'odm' => 'date_immutable'],
        'date'               => ['php' => '\DateTimeImmutable', 'odm' => 'date_immutable'],
        'time'               => ['php' => '\DateTimeImmutable', 'odm' => 'date_immutable'],
        'uuid'               => ['php' => 'string',             'odm' => 'string'],
    ];

    public function generate(Model $model): string
    {
        $class = $model->name;

        $indexes = [];
        foreach ($model->indexes as $idx) {
            $fields = $idx['fields'] ?? [];
            if (!\is_array($fields) || $fields === []) {
                continue;
            }
            $kv = [];
            foreach ($fields as $f => $dir) {
                $kv[] = "'{$f}' => " . (int) $dir;
            }
            $unique = !empty($idx['unique']) ? ', unique: true' : '';
            $sparse = !empty($idx['sparse']) ? ', sparse: true' : '';
            $indexes[] = "#[ODM\\Index(keys: [" . implode(', ', $kv) . "]{$unique}{$sparse})]";
        }

        [$propertyBlock, $methodBlock, $ctorBody] = $this->buildBody($model);

        $constructor = $ctorBody === ''
            ? ''
            : "    public function __construct()\n    {\n{$ctorBody}    }\n\n";

        $description = $model->description ?? "Generated mongo document for {$class}.";
        $indexBlock = $indexes === [] ? '' : "\n" . implode("\n", $indexes);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Document;

use Doctrine\\ODM\\MongoDB\\Mapping\\Annotations as ODM;
use Symfony\\Component\\Validator\\Constraints as Assert;

/**
 * {$description}
 *
 * @generated baas-codegen
 */
#[ODM\\Document(collection: '{$model->table}')]{$indexBlock}
class {$class}
{
{$propertyBlock}
{$constructor}{$methodBlock}}

PHP;
    }

    /** @return array{0:string,1:string,2:string} */
    private function buildBody(Model $model): array
    {
        $props = [];
        $methods = [];
        $ctorLines = [];

        foreach ($model->fields as $f) {
            if ($f->isPrimary()) {
                $props[] = <<<PHP
    #[ODM\\Id]
    private ?string \${$f->name} = null;
PHP;
                $methods[] = <<<PHP
    public function get{$f->name}(): ?string
    {
        return \$this->{$f->name};
    }
PHP;
                continue;
            }

            $info = self::TYPE_MAP[$f->type] ?? self::TYPE_MAP['string'];
            $phpType = $info['php'];
            $odmType = $info['odm'];

            $args = ["type: '{$odmType}'"];
            if ($f->nullable) {
                $args[] = 'nullable: true';
            }
            $attr = '#[ODM\\Field(' . implode(', ', $args) . ')]';

            $assertList = $f->assert;
            if ($f->type === 'email' && !\in_array('Email', $assertList, true)) {
                $assertList[] = 'Email';
            }
            if (!$f->nullable && !$f->isDateTimeType() && !\in_array('NotBlank', $assertList, true) && $f->type !== 'bool') {
                $assertList[] = 'NotBlank';
            }
            $assertAttrs = '';
            foreach (array_unique($assertList) as $a) {
                $assertAttrs .= "\n    #[Assert\\{$a}]";
            }

            $nullable = $f->nullable ? '?' : '';
            $defaultExpr = '';
            if ($f->default === 'now' && $f->isDateTimeType()) {
                $ctorLines[] = "        \$this->{$f->name} = new \\DateTimeImmutable();\n";
            } elseif ($f->default !== null && \is_scalar($f->default)) {
                $defaultExpr = ' = ' . var_export($f->default, true);
            } elseif ($f->nullable) {
                $defaultExpr = ' = null';
            } elseif ($phpType === 'array') {
                $defaultExpr = ' = []';
            } elseif ($phpType === 'string') {
                $defaultExpr = " = ''";
            } elseif ($phpType === 'bool') {
                $defaultExpr = ' = false';
            } elseif ($phpType === 'int' || $phpType === 'float') {
                $defaultExpr = ' = 0';
            }

            $props[] = <<<PHP
    {$attr}{$assertAttrs}
    private {$nullable}{$phpType} \${$f->name}{$defaultExpr};
PHP;

            $cap = ucfirst($f->name);
            $methods[] = <<<PHP
    public function get{$cap}(): {$nullable}{$phpType}
    {
        return \$this->{$f->name};
    }

    public function set{$cap}({$nullable}{$phpType} \${$f->name}): self
    {
        \$this->{$f->name} = \${$f->name};
        return \$this;
    }
PHP;
        }

        return [implode("\n\n", $props) . "\n", implode("\n\n", $methods) . "\n", implode('', $ctorLines)];
    }
}
