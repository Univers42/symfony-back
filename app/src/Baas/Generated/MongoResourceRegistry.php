<?php

declare(strict_types=1);

namespace App\Baas\Generated;

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
        'audit_logs' => \App\Document\AuditLog::class,
        'gallery_images' => \App\Document\GalleryImage::class,
        ];
    }

    public static function classFor(string $slug): ?string
    {
        return self::all()[$slug] ?? null;
    }
}
