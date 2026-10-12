<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Test\Unit\Model\Image;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenLabel\Model\Config;
use Iranimij\OpenLabel\Model\Image\SvgSanitizer;
use Iranimij\OpenLabel\Model\Image\Validator;
use Magento\Framework\Filesystem\Driver\File;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    /** @var string[] */
    private array $files = [];

    /** the "largest image upload" setting in KB, 0 = not set */
    private int $maxKb = 0;

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'olimg');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }

    /** A real 2 × 3 px PNG (GD is a Magento requirement). */
    private function png(): string
    {
        $image = imagecreatetruecolor(2, 3);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function validator(): Validator
    {
        $reader = $this->createStub(TypedReader::class);
        $reader->method('getInt')->willReturnCallback(fn (): int => $this->maxKb);

        return new Validator(new SvgSanitizer(), new File(), new Config($reader));
    }

    /**
     * An AVIF header as written by encoders: `ftyp` with the avif brand, then the image size in an `ispe` box.
     */
    private function avif(int $width, int $height, string $brand = 'avif'): string
    {
        $ftyp = pack('N', 28) . 'ftyp' . $brand . pack('N', 0) . 'mif1miafMA1B';
        $ispe = pack('N', 20) . 'ispe' . pack('N', 0) . pack('N', $width) . pack('N', $height);
        $meta = pack('N', 8 + 12 + strlen($ispe)) . 'meta' . pack('N', 0) . pack('N', 8 + strlen($ispe)) . 'iprp' . $ispe;

        return $ftyp . $meta . str_repeat("\0", 64);
    }

    public function testAvifIsAcceptedWithItsDimensions(): void
    {
        $result = $this->validator()->validate($this->file($this->avif(120, 40)), 'sale.avif');

        self::assertSame([], $result->getErrors());
        self::assertSame('avif', $result->getExtension());
        self::assertSame([120, 40], [$result->getWidth(), $result->getHeight()]);
    }

    public function testAFileNamedAvifWithoutTheAvifBrandIsRejected(): void
    {
        $video = $this->validator()->validate($this->file($this->avif(120, 40, 'mp42')), 'clip.avif');
        $text = $this->validator()->validate($this->file('hello world, not an image at all'), 'note.avif');

        foreach ([$video, $text] as $result) {
            self::assertSame(
                ['The file content does not match its extension. Upload a real PNG, JPG, GIF, WebP, AVIF or SVG image.'],
                array_map('strval', $result->getErrors())
            );
        }
    }

    public function testAnAvifWithoutDimensionsIsRejected(): void
    {
        $noSize = substr($this->avif(1, 1), 0, 28) . str_repeat("\0", 64);

        self::assertNotSame([], $this->validator()->validate($this->file($noSize), 'broken.avif')->getErrors());
    }

    public function testTheUploadLimitFollowsTheSetting(): void
    {
        $this->maxKb = 200;
        $png = $this->png();

        $over = $this->validator()->validate($this->file($png . str_repeat("\0", 210 * 1024)), 'over.png');
        $under = $this->validator()->validate($this->file($png . str_repeat("\0", 150 * 1024)), 'under.png');

        self::assertSame(['The image is larger than 200 KB. Use a smaller file; SVG works best.'], array_map('strval', $over->getErrors()));
        self::assertSame([], $under->getErrors());
    }

    public function testPngIsAcceptedWithItsDimensions(): void
    {
        $result = $this->validator()->validate($this->file($this->png()), 'sale.png');

        self::assertSame([], $result->getErrors());
        self::assertSame('png', $result->getExtension());
        self::assertSame(2, $result->getWidth());
        self::assertSame(3, $result->getHeight());
        self::assertSame([], $result->getWarnings());
    }

    public function testSvgIsAcceptedAndSanitized(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="24" onload="x()"><rect width="80" height="24"/></svg>';

        $result = $this->validator()->validate($this->file($svg), 'Badge.SVG');

        self::assertSame([], $result->getErrors());
        self::assertSame('svg', $result->getExtension());
        self::assertSame([80, 24], [$result->getWidth(), $result->getHeight()]);
        self::assertStringNotContainsString('onload', (string) $result->getSanitizedContents());
    }

    public function testPhpDisguisedAsPngIsRejected(): void
    {
        $result = $this->validator()->validate($this->file('<?php echo "x";'), 'shell.png');

        self::assertSame(['The file content does not match its extension. Upload a real PNG, JPG, GIF, WebP, AVIF or SVG image.'], array_map('strval', $result->getErrors()));
    }

    public function testUnsupportedExtensionIsRejected(): void
    {
        $result = $this->validator()->validate($this->file('x'), 'label.php');

        self::assertSame(['Allowed image types are PNG, JPG, GIF, WebP, AVIF and SVG.'], array_map('strval', $result->getErrors()));
    }

    public function testFilesOver50KbGetAWarningAndOver1MbAnError(): void
    {
        $png = $this->png();

        $big = $this->validator()->validate($this->file($png . str_repeat("\0", 60 * 1024)), 'big.png');
        self::assertSame([], $big->getErrors());
        self::assertStringContainsString('larger than 50 KB', (string) ($big->getWarnings()[0] ?? ''));

        $huge = $this->validator()->validate($this->file($png . str_repeat("\0", 1100 * 1024)), 'huge.png');
        self::assertStringContainsString('1 MB', (string) ($huge->getErrors()[0] ?? ''));
    }

    public function testSvgWithEntitiesIsRejectedWithTheSanitizerReason(): void
    {
        $svg = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x "y">]><svg xmlns="http://www.w3.org/2000/svg"/>';

        $result = $this->validator()->validate($this->file($svg), 'evil.svg');

        self::assertSame(['SVG files with a DOCTYPE or entities are not accepted.'], array_map('strval', $result->getErrors()));
    }
}
