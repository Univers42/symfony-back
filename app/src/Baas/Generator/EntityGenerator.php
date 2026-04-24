<?php

declare(strict_types=1);

namespace App\Baas\Generator;

use App\Baas\Model\Field;
use App\Baas\Model\Model;
use App\Baas\Model\Relation;

/**
 * Emits a Doctrine ORM entity class for a Postgres-backed model, with
 * #[ApiResource] when api.enabled is true.
 *
 * The output file is marked "@generated baas-codegen" and will be safely
 * overwritten on subsequent runs. Files lacking that marker are NEVER
 * overwritten — write your customizations in <Name>Trait.php instead.
 */
final class EntityGenerator
{
    public const MARKER = '@generated baas-codegen';

    /** Doctrine type literal mapped to PHP scalar type and Doctrine\DBAL\Types\Types const. */
    private const TYPE_MAP = [
        'int'                 => ['php' => 'int',                 'doctrine' => 'INTEGER'],
        'bigint'              => ['php' => 'string',              'doctrine' => 'BIGINT'],
        'smallint'            => ['php' => 'int',                 'doctrine' => 'SMALLINT'],
        'float'               => ['php' => 'float',               'doctrine' => 'FLOAT'],
        'decimal'             => ['php' => 'string',              'doctrine' => 'DECIMAL'],
        'bool'                => ['php' => 'bool',                'doctrine' => 'BOOLEAN'],
        'string'              => ['php' => 'string',              'doctrine' => 'STRING'],
        'email'               => ['php' => 'string',              'doctrine' => 'STRING'],
        'url'                 => ['php' => 'string',              'doctrine' => 'STRING'],
        'text'                => ['php' => 'string',              'doctrine' => 'TEXT'],
        'json'                => ['php' => 'array',               'doctrine' => 'JSON'],
        'datetime_immutable'  => ['php' => '\DateTimeImmutable',  'doctrine' => 'DATETIME_IMMUTABLE'],
        'date'                => ['php' => '\DateTimeImmutable',  'doctrine' => 'DATE_IMMUTABLE'],
        'time'                => ['php' => '\DateTimeImmutable',  'doctrine' => 'TIME_IMMUTABLE'],
        'uuid'                => ['php' => 'string',              'doctrine' => 'GUID'],
    ];

    public function generate(Model $model): string
    {
        $class    = $model->name;
        $repoFqcn = "App\\Repository\\{$class}Repository";

        $uses = [
            "App\\Repository\\{$class}Repository",
            'Doctrine\\DBAL\\Types\\Types',
            'Doctrine\\ORM\\Mapping as ORM',
            'Symfony\\Component\\Validator\\Constraints as Assert',
            'Symfony\\Component\\Serializer\\Attribute\\Groups',
        ];

        $api = $model->api;
        if (($api['enabled'] ?? true) === true) {
            $uses[] = 'ApiPlatform\\Metadata\\ApiResource';
            $uses[] = 'ApiPlatform\\Metadata\\GetCollection';
            $uses[] = 'ApiPlatform\\Metadata\\Get';
            $uses[] = 'ApiPlatform\\Metadata\\Post';
            $uses[] = 'ApiPlatform\\Metadata\\Put';
            $uses[] = 'ApiPlatform\\Metadata\\Patch';
            $uses[] = 'ApiPlatform\\Metadata\\Delete';
        }

        $hasCollection = false;
        foreach ($model->relations as $r) {
            if (\in_array($r->type, ['one_to_many', 'many_to_many'], true)) {
                $hasCollection = true;
                break;
            }
        }
        if ($hasCollection) {
            $uses[] = 'Doctrine\\Common\\Collections\\ArrayCollection';
            $uses[] = 'Doctrine\\Common\\Collections\\Collection';
        }

        $uses = array_values(array_unique($uses));
        sort($uses);

        $usesBlock = implode("\n", array_map(static fn (string $u) => "use {$u};", $uses));

        $classAttrs = [
            "#[ORM\\Entity(repositoryClass: {$class}Repository::class)]",
            "#[ORM\\Table(name: '{$model->table}')]",
        ];
        foreach ($model->indexes as $idx) {
            $name = $idx['name'] ?? null;
            $cols = $idx['columns'] ?? [];
            if (!$name || !\is_array($cols) || $cols === []) {
                continue;
            }
            $colList = "['" . implode("', '", $cols) . "']";
            $cls = ($idx['unique'] ?? false) ? 'UniqueConstraint' : 'Index';
            $classAttrs[] = "#[ORM\\{$cls}(name: '{$name}', columns: {$colList})]";
        }
        $classAttrs[] = $this->buildApiResourceAttribute($model);

        $classAttrsBlock = implode("\n", array_filter($classAttrs));

        // Build properties + methods.
        [$propertyBlock, $methodBlock, $constructorBody] = $this->buildBody($model);

        $constructor = $constructorBody === ''
            ? ''
            : "    public function __construct()\n    {\n{$constructorBody}    }\n\n";

        $description = $model->description ?? "Generated entity for {$class}.";

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Entity;

{$usesBlock}

/**
 * {$description}
 *
 * {$this->markerLine()}
 */
{$classAttrsBlock}
class {$class}
{
{$propertyBlock}
{$constructor}{$methodBlock}}

PHP;
    }

    public function generateRepository(Model $model): string
    {
        $class = $model->name;

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Repository;

use App\\Entity\\{$class};
use Doctrine\\Bundle\\DoctrineBundle\\Repository\\ServiceEntityRepository;
use Doctrine\\Persistence\\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<{$class}>
 *
 * {$this->markerLine()}
 */
class {$class}Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry \$registry)
    {
        parent::__construct(\$registry, {$class}::class);
    }
}

PHP;
    }

    private function markerLine(): string
    {
        return self::MARKER;
    }

    /** @return array{0:string,1:string,2:string} [propertyBlock, methodBlock, constructorBody] */
    private function buildBody(Model $model): array
    {
        $props = [];
        $methods = [];
        $ctorLines = [];

        foreach ($model->fields as $f) {
            if ($f->isPrimary()) {
                $props[] = $this->renderIdProperty($f);
                $methods[] = $this->renderIdAccessor($f);
                continue;
            }

            $props[] = $this->renderScalarProperty($model, $f, $ctorLines);
            $methods[] = $this->renderScalarAccessors($model, $f);
        }

        foreach ($model->relations as $r) {
            $props[] = $this->renderRelationProperty($r);
            $methods[] = $this->renderRelationAccessors($r);
            if (\in_array($r->type, ['one_to_many', 'many_to_many'], true)) {
                $ctorLines[] = "        \$this->{$r->name} = new ArrayCollection();\n";
            }
        }

        return [implode("\n\n", $props) . "\n", implode("\n\n", $methods) . "\n", implode('', $ctorLines)];
    }

    private function renderIdProperty(Field $f): string
    {
        return <<<PHP
    #[ORM\\Id]
    #[ORM\\GeneratedValue]
    #[ORM\\Column(type: Types::INTEGER)]
    #[Groups(['{$this->placeholderReadGroup($f)}'])]
    private ?int \${$f->name} = null;
PHP;
    }

    private function placeholderReadGroup(Field $f): string
    {
        // Default group; per-model override possible later via $f->groups.
        return ($f->groups[0] ?? 'default:read');
    }

    private function renderIdAccessor(Field $f): string
    {
        $name = $f->name;
        $cap  = ucfirst($name);

        return <<<PHP
    public function get{$cap}(): ?int
    {
        return \$this->{$name};
    }
PHP;
    }

    /** @param list<string> $ctorLines */
    private function renderScalarProperty(Model $model, Field $f, array &$ctorLines): string
    {
        $info = self::TYPE_MAP[$f->type] ?? self::TYPE_MAP['string'];
        $phpType = $info['php'];
        $doctrineConst = $info['doctrine'];

        $columnArgs = ["type: Types::{$doctrineConst}"];
        if ($f->length !== null && \in_array($f->type, ['string', 'email', 'url', 'uuid'], true)) {
            $columnArgs[] = "length: {$f->length}";
        }
        if ($f->nullable) {
            $columnArgs[] = 'nullable: true';
        }
        if ($f->unique) {
            $columnArgs[] = 'unique: true';
        }

        $attrs = [];
        $attrs[] = '#[ORM\\Column(' . implode(', ', $columnArgs) . ')]';

        $assertList = $f->assert;
        if ($f->type === 'email' && !\in_array('Email', $assertList, true)) {
            $assertList[] = 'Email';
        }
        if ($f->type === 'url' && !\in_array('Url', $assertList, true)) {
            $assertList[] = 'Url';
        }
        if (!$f->nullable && !$f->isDateTimeType() && !\in_array('NotBlank', $assertList, true) && !\in_array('NotNull', $assertList, true)) {
            $assertList[] = $f->type === 'bool' ? 'NotNull' : 'NotBlank';
        }
        foreach (array_unique($assertList) as $a) {
            $attrs[] = "#[Assert\\{$a}]";
        }

        $groupRead = $f->groups[0] ?? sprintf('%s:read', $model->snake());
        $groupWrite = $f->groups[1] ?? sprintf('%s:write', $model->snake());
        $attrs[] = "#[Groups(['{$groupRead}', '{$groupWrite}'])]";

        $nullable = $f->nullable ? '?' : '';
        $defaultExpr = '';

        if ($f->default === 'now' && $f->isDateTimeType()) {
            $ctorLines[] = "        \$this->{$f->name} = new \\DateTimeImmutable();\n";
        } elseif ($f->default === 'uuid' && $f->type === 'uuid') {
            $ctorLines[] = "        \$this->{$f->name} = bin2hex(random_bytes(16));\n";
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

        $attrBlock = '    ' . implode("\n    ", $attrs);

        return <<<PHP
{$attrBlock}
    private {$nullable}{$phpType} \${$f->name}{$defaultExpr};
PHP;
    }

    private function renderScalarAccessors(Model $model, Field $f): string
    {
        $info = self::TYPE_MAP[$f->type] ?? self::TYPE_MAP['string'];
        $phpType = $info['php'];
        $nullable = $f->nullable ? '?' : '';
        $name = $f->name;
        $cap  = ucfirst($name);

        return <<<PHP
    public function get{$cap}(): {$nullable}{$phpType}
    {
        return \$this->{$name};
    }

    public function set{$cap}({$nullable}{$phpType} \${$name}): self
    {
        \$this->{$name} = \${$name};
        return \$this;
    }
PHP;
    }

    private function renderRelationProperty(Relation $r): string
    {
        $name = $r->name;
        $target = $r->target;
        $args = ["targetEntity: {$target}::class"];

        if ($r->mappedBy !== null) {
            $args[] = "mappedBy: '{$r->mappedBy}'";
        }
        if ($r->inversedBy !== null) {
            $args[] = "inversedBy: '{$r->inversedBy}'";
        }
        if ($r->cascade !== []) {
            $args[] = "cascade: ['" . implode("', '", $r->cascade) . "']";
        }

        $argList = implode(', ', $args);

        switch ($r->type) {
            case 'many_to_one':
                $attrs = [
                    "#[ORM\\ManyToOne({$argList})]",
                ];
                if ($r->onDelete !== null || !$r->nullable) {
                    $jc = ["nullable: " . ($r->nullable ? 'true' : 'false')];
                    if ($r->onDelete !== null) {
                        $jc[] = "onDelete: '{$r->onDelete}'";
                    }
                    $attrs[] = "#[ORM\\JoinColumn(" . implode(', ', $jc) . ")]";
                }
                $type = "?{$target}";
                $defaultExpr = ' = null';
                break;

            case 'one_to_one':
                $attrs = ["#[ORM\\OneToOne({$argList})]"];
                $type  = "?{$target}";
                $defaultExpr = ' = null';
                break;

            case 'one_to_many':
                $attrs = ["#[ORM\\OneToMany({$argList})]"];
                $type = 'Collection';
                $defaultExpr = '';
                break;

            case 'many_to_many':
                $attrs = ["#[ORM\\ManyToMany({$argList})]"];
                $type = 'Collection';
                $defaultExpr = '';
                break;

            default:
                $attrs = [];
                $type = 'mixed';
                $defaultExpr = '';
        }

        $attrBlock = '    ' . implode("\n    ", $attrs);

        return <<<PHP
{$attrBlock}
    private {$type} \${$name}{$defaultExpr};
PHP;
    }

    private function renderRelationAccessors(Relation $r): string
    {
        $name = $r->name;
        $cap  = ucfirst($name);
        $target = $r->target;

        if (\in_array($r->type, ['one_to_many', 'many_to_many'], true)) {
            return <<<PHP
    /** @return Collection<int, {$target}> */
    public function get{$cap}(): Collection
    {
        return \$this->{$name};
    }

    public function add{$cap}({$target} \$item): self
    {
        if (!\$this->{$name}->contains(\$item)) {
            \$this->{$name}->add(\$item);
        }
        return \$this;
    }

    public function remove{$cap}({$target} \$item): self
    {
        \$this->{$name}->removeElement(\$item);
        return \$this;
    }
PHP;
        }

        return <<<PHP
    public function get{$cap}(): ?{$target}
    {
        return \$this->{$name};
    }

    public function set{$cap}(?{$target} \${$name}): self
    {
        \$this->{$name} = \${$name};
        return \$this;
    }
PHP;
    }

    private function buildApiResourceAttribute(Model $model): string
    {
        $api = $model->api;
        if (($api['enabled'] ?? true) !== true) {
            return '';
        }

        $opMap = [
            'GetCollection' => 'GetCollection',
            'Get'           => 'Get',
            'Post'          => 'Post',
            'Put'           => 'Put',
            'Patch'         => 'Patch',
            'Delete'        => 'Delete',
        ];
        $ops = $api['operations'] ?? ['GetCollection', 'Get', 'Post', 'Put', 'Delete'];
        $sec = $api['security_per_op'] ?? [];

        $opEntries = [];
        foreach ($ops as $op) {
            $cls = $opMap[$op] ?? null;
            if ($cls === null) {
                continue;
            }
            $args = [];
            if (isset($sec[$op])) {
                $args[] = "security: \"is_granted('{$sec[$op]}')\"";
            }
            $argList = $args === [] ? '' : '(' . implode(', ', $args) . ')';
            $opEntries[] = "        new {$cls}{$argList}";
        }

        $normGroups = $api['normalization_groups']   ?? [sprintf('%s:read', $model->snake())];
        $denormGroups = $api['denormalization_groups'] ?? [sprintf('%s:write', $model->snake())];
        $perPage = (int) ($api['pagination_items_per_page'] ?? 30);

        $normList   = "['" . implode("', '", $normGroups) . "']";
        $denormList = "['" . implode("', '", $denormGroups) . "']";

        return "#[ApiResource(\n"
            . "    operations: [\n" . implode(",\n", $opEntries) . ",\n    ],\n"
            . "    normalizationContext: ['groups' => {$normList}],\n"
            . "    denormalizationContext: ['groups' => {$denormList}],\n"
            . "    paginationItemsPerPage: {$perPage},\n"
            . ')]';
    }
}
