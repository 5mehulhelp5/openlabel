<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\ImportExport;

use Iranimij\OpenLabel\Model\ImportExport\FileFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FileFormatTest extends TestCase
{
    public function testAValidFileHasNoErrors(): void
    {
        self::assertSame([], (new FileFormat())->errors($this->file()));
    }

    /**
     * @dataProvider broken
     * @param array<string, mixed> $change
     */
    #[DataProvider('broken')]
    public function testStructuralErrorsAreReported(array $change, string $expected): void
    {
        $errors = (new FileFormat())->errors(array_replace_recursive($this->file(), $change));

        self::assertContains($expected, $errors);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function broken(): array
    {
        return [
            'not an OpenLabel file' => [['format' => 'other'], 'This is not an OpenLabel export file.'],
            'newer version' => [['version' => 3], 'The file has format version 3; this OpenLabel reads versions 1 and 2.'],
            'no version' => [['version' => null], 'The file has format version ?; this OpenLabel reads versions 1 and 2.'],
            'design without key' => [['designs' => [1 => ['key' => '']]], 'designs[1]: a design needs a "key".'],
            'unknown design reference' => [
                ['labels' => [0 => ['design' => 'design:99']]],
                'labels[0] "Sale": design "design:99" is neither in the file nor a built-in design reference.',
            ],
            'unknown placement design' => [
                ['labels' => [0 => ['placements' => [0 => ['design' => 'design:77']]]]],
                'labels[0] "Sale": placement 1 uses design "design:77", which is not in the file.',
            ],
            'label without name' => [['labels' => [0 => ['name' => '']]], 'labels[0]: a label needs a name.'],
            'conditions not a tree' => [['labels' => [0 => ['conditions' => 'x']]], 'labels[0] "Sale": "conditions" must be a condition tree or null.'],
        ];
    }

    public function testVersionOneAndTwoFilesAreBothRead(): void
    {
        foreach ([1, 2] as $version) {
            $file = $this->file();
            $file['version'] = $version;
            self::assertSame([], (new FileFormat())->errors($file), 'version ' . $version);
        }
    }

    public function testDuplicateDesignKeys(): void
    {
        $file = $this->file();
        $file['designs'][] = $file['designs'][0];

        self::assertContains('designs[2]: the key "design:5" is used twice.', (new FileFormat())->errors($file));
    }

    public function testMissingListsAreReported(): void
    {
        self::assertSame(
            ['"designs" must be a list.', '"labels" must be a list.'],
            (new FileFormat())->errors(['format' => 'openlabel', 'version' => 1])
        );
    }

    public function testSystemDesignReferencesNeedNoEntryInTheFile(): void
    {
        $file = $this->file();
        $file['labels'][0]['design'] = 'system:Sale (red pill)';

        self::assertSame([], (new FileFormat())->errors($file));
    }

    /**
     * @return array<string, mixed>
     */
    private function file(): array
    {
        return [
            'format' => 'openlabel',
            'version' => 1,
            'designs' => [
                ['key' => 'design:5', 'name' => 'Red', 'type' => 'text'],
                ['key' => 'system:New (green)', 'name' => 'New (green)', 'is_system' => true],
            ],
            'labels' => [
                [
                    'name' => 'Sale',
                    'design' => 'design:5',
                    'conditions' => ['type' => 'X', 'conditions' => []],
                    'placements' => [['area' => 'listing', 'position' => 'tl', 'design' => null]],
                ],
            ],
        ];
    }
}
