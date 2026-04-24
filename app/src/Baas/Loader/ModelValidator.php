<?php

declare(strict_types=1);

namespace App\Baas\Loader;

use App\Baas\Model\Model;

/**
 * Validates a list of loaded models for cross-references and field types.
 * Returns a list of error strings (empty list = valid).
 */
final class ModelValidator
{
    public const ALLOWED_FIELD_TYPES = [
        'id', 'int', 'bigint', 'smallint', 'float', 'decimal', 'bool',
        'string', 'text', 'json',
        'datetime_immutable', 'date', 'time', 'uuid', 'email', 'url',
    ];

    public const ALLOWED_RELATION_TYPES = ['many_to_one', 'one_to_many', 'many_to_many', 'one_to_one'];
    public const ALLOWED_STORES         = ['postgres', 'mongo'];

    /**
     * @param list<Model> $models
     * @return list<string>
     */
    public function validate(array $models): array
    {
        $errors = [];
        $byName = [];

        foreach ($models as $m) {
            if (isset($byName[$m->name])) {
                $errors[] = \sprintf('Duplicate model name "%s" (in %s).', $m->name, $m->sourcePath);
                continue;
            }
            $byName[$m->name] = $m;

            if (!\in_array($m->store, self::ALLOWED_STORES, true)) {
                $errors[] = \sprintf('Model "%s": store "%s" is not in [%s].', $m->name, $m->store, implode(',', self::ALLOWED_STORES));
            }

            $primaryCount = 0;
            $seenFieldNames = [];
            foreach ($m->fields as $f) {
                if (isset($seenFieldNames[$f->name])) {
                    $errors[] = \sprintf('Model "%s": duplicate field "%s".', $m->name, $f->name);
                }
                $seenFieldNames[$f->name] = true;

                if (!\in_array($f->type, self::ALLOWED_FIELD_TYPES, true)) {
                    $errors[] = \sprintf('Model "%s.%s": unsupported type "%s".', $m->name, $f->name, $f->type);
                }
                if ($f->isPrimary()) {
                    $primaryCount++;
                }
            }
            if ($primaryCount !== 1) {
                $errors[] = \sprintf('Model "%s": expected exactly 1 field of type "id", got %d.', $m->name, $primaryCount);
            }

            if ($m->isMongo() && !empty($m->relations)) {
                $errors[] = \sprintf('Model "%s" (mongo): relations are not supported on mongo models — store ids inline.', $m->name);
            }
        }

        // Cross-reference relations after all models are indexed.
        foreach ($models as $m) {
            foreach ($m->relations as $r) {
                if (!\in_array($r->type, self::ALLOWED_RELATION_TYPES, true)) {
                    $errors[] = \sprintf('Model "%s.%s": unsupported relation type "%s".', $m->name, $r->name, $r->type);
                }
                if (!isset($byName[$r->target])) {
                    $errors[] = \sprintf('Model "%s.%s": relation target "%s" is not a known model.', $m->name, $r->name, $r->target);
                }
            }
        }

        return $errors;
    }
}
