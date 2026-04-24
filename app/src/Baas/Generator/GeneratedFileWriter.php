<?php

declare(strict_types=1);

namespace App\Baas\Generator;

use App\Baas\Model\Model;

/**
 * Atomic-ish file writer for generated PHP sources.
 *
 * Safety contract: a generated file is identified by the presence of the
 * marker substring "@generated baas-codegen" in its body. If the destination
 * file exists but lacks that marker, write() refuses to overwrite — protecting
 * any hand-written file with the same name (e.g. App\Entity\User).
 */
final class GeneratedFileWriter
{
    public const MARKER = '@generated baas-codegen';

    /** @return array{written:int, skipped:int, paths_written:list<string>, paths_skipped:list<string>} */
    public function writeMany(iterable $items, bool $force = false): array
    {
        $written = 0;
        $skipped = 0;
        $w = [];
        $s = [];
        foreach ($items as $item) {
            [$path, $contents] = [$item['path'], $item['contents']];
            if ($this->write($path, $contents, $force)) {
                $written++;
                $w[] = $path;
            } else {
                $skipped++;
                $s[] = $path;
            }
        }

        return ['written' => $written, 'skipped' => $skipped, 'paths_written' => $w, 'paths_skipped' => $s];
    }

    public function write(string $path, string $contents, bool $force = false): bool
    {
        if (!str_contains($contents, self::MARKER)) {
            throw new \LogicException(\sprintf(
                'Generated content for %s is missing the "%s" marker — refusing to write.',
                $path, self::MARKER,
            ));
        }

        if (!$force && is_file($path)) {
            $existing = (string) file_get_contents($path);
            if (!str_contains($existing, self::MARKER)) {
                // Hand-written file — never overwrite.
                return false;
            }
        }

        $dir = \dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Cannot create directory %s.', $dir));
        }

        $tmp = $path . '.tmp';
        file_put_contents($tmp, $contents);
        rename($tmp, $path);

        return true;
    }
}
