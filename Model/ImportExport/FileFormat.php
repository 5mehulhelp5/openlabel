<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\ImportExport;

/**
 * The OpenLabel export file (JSON): `{"format": "openlabel", "version": 2, "designs": [...], "labels": [...]}`.
 * Version 2 (1.1) adds design animation, hover effect and sizes, and label hide-on-zero and click-through fields;
 * version 1 files are still read, the new fields taking their defaults.
 * Designs carry a `key` (`design:<id>` for the merchant's designs, `system:<name>` for built-in ones) that labels and
 * placements reference; store views and customer groups are referenced by code, so a file moves between shops.
 * This class checks the structure only; the importer checks the contents against the shop.
 */
class FileFormat
{
    public const NAME = 'openlabel';
    public const VERSION = 2;
    public const READABLE_VERSIONS = [1, 2];
    public const SYSTEM_PREFIX = 'system:';
    public const DESIGN_PREFIX = 'design:';

    /**
     * @param array<string, mixed> $data the decoded file
     * @return string[] what is wrong, empty when the structure is fine
     */
    public function errors(array $data): array
    {
        if (($data['format'] ?? null) !== self::NAME) {
            return ['This is not an OpenLabel export file.'];
        }
        if (!in_array((int) ($data['version'] ?? 0), self::READABLE_VERSIONS, true)) {
            return [sprintf(
                'The file has format version %s; this OpenLabel reads versions %s.',
                (string) ($data['version'] ?? '?'),
                implode(' and ', self::READABLE_VERSIONS)
            )];
        }
        $errors = [];
        if (!is_array($data['designs'] ?? null) || !array_is_list($data['designs'])) {
            $errors[] = '"designs" must be a list.';
        }
        if (!is_array($data['labels'] ?? null) || !array_is_list($data['labels'])) {
            $errors[] = '"labels" must be a list.';
        }
        if ($errors !== []) {
            return $errors;
        }

        $keys = [];
        foreach ($data['designs'] as $i => $design) {
            $key = is_array($design) ? ($design['key'] ?? null) : null;
            if (!is_string($key) || $key === '') {
                $errors[] = sprintf('designs[%d]: a design needs a "key".', $i);
                continue;
            }
            if (isset($keys[$key])) {
                $errors[] = sprintf('designs[%d]: the key "%s" is used twice.', $i, $key);
            }
            $keys[$key] = true;
        }
        foreach ($data['labels'] as $i => $label) {
            $errors = array_merge($errors, $this->labelErrors($i, $label, $keys));
        }

        return $errors;
    }

    /**
     * @param int $index
     * @param mixed $label
     * @param array<string, true> $keys design keys present in the file
     * @return string[]
     */
    private function labelErrors(int $index, mixed $label, array $keys): array
    {
        if (!is_array($label) || trim((string) ($label['name'] ?? '')) === '') {
            return [sprintf('labels[%d]: a label needs a name.', $index)];
        }
        $where = sprintf('labels[%d] "%s"', $index, $label['name']);
        $errors = [];
        $design = (string) ($label['design'] ?? '');
        if (!isset($keys[$design]) && !str_starts_with($design, self::SYSTEM_PREFIX)) {
            $errors[] = sprintf('%s: design "%s" is neither in the file nor a built-in design reference.', $where, $design);
        }
        if (array_key_exists('conditions', $label) && $label['conditions'] !== null && !is_array($label['conditions'])) {
            $errors[] = sprintf('%s: "conditions" must be a condition tree or null.', $where);
        }
        $placements = $label['placements'] ?? [];
        if (!is_array($placements) || !array_is_list($placements)) {
            return array_merge($errors, [sprintf('%s: "placements" must be a list.', $where)]);
        }
        foreach ($placements as $p => $placement) {
            $override = is_array($placement) ? ($placement['design'] ?? null) : null;
            if ($override !== null && !isset($keys[(string) $override]) && !str_starts_with((string) $override, self::SYSTEM_PREFIX)) {
                $errors[] = sprintf('%s: placement %d uses design "%s", which is not in the file.', $where, $p + 1, (string) $override);
            }
        }

        return $errors;
    }
}
