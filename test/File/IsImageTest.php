<?php

declare(strict_types=1);

namespace LaminasTest\Validator\File;

use Laminas\Validator\File\IsImage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function basename;
use function current;
use function extension_loaded;
use function is_array;

use const PHP_VERSION_ID;

final class IsImageTest extends TestCase
{
    protected function getMagicMime(): string
    {
        return __DIR__ . '/_files/magic.7.mime';
    }

    /**
     * @psalm-return array<array{list<string>|string|null, array<string, string|int>, bool}>
     */
    public static function basicBehaviorDataProvider(): array
    {
        $testFile   = __DIR__ . '/_files/picture.jpg';
        $fileUpload = [
            'tmp_name' => $testFile,
            'name'     => basename($testFile),
            'size'     => 200,
            'error'    => 0,
            'type'     => 'image/jpeg',
        ];

        return [
            //    Options, isValid Param, Expected value
            [null,                         $fileUpload, true],
            ['jpeg',                       $fileUpload, true],
            ['test/notype',                $fileUpload, false],
            ['image/gif, image/jpeg',      $fileUpload, true],
            [['image/vasa', 'image/jpeg'], $fileUpload, true],
            [['image/jpeg', 'gif'], $fileUpload, true],
            [['image/gif', 'gif'], $fileUpload, false],
            ['image/jp',                   $fileUpload, false],
            ['image/jpg2000',              $fileUpload, false],
            ['image/jpeg2000',             $fileUpload, false],
        ];
    }

    /**
     * Ensures that the validator follows expected behavior
     *
     * @psalm-param list<string>|string|null $options
     * @param array<string, string|int> $isValidParam
     */
    #[DataProvider('basicBehaviorDataProvider')]
    public function testBasic($options, array $isValidParam, bool $expected): void
    {
        $validator = new IsImage($options);
        $validator->enableHeaderCheck();

        self::assertSame($expected, $validator->isValid($isValidParam));
    }

    /**
     * Ensures that the validator follows expected behavior for legacy Laminas\Transfer API
     *
     * @psalm-param list<string>|string|null $options
     * @param array<string, string|int> $isValidParam
     */
    #[DataProvider('basicBehaviorDataProvider')]
    public function testLegacy($options, array $isValidParam, bool $expected): void
    {
        if (is_array($isValidParam)) {
            $validator = new IsImage($options);
            $validator->enableHeaderCheck();

            self::assertSame($expected, $validator->isValid($isValidParam['tmp_name'], $isValidParam));
        }
    }

    /** @psalm-return array<array{string|string[], string|string[], bool}> */
    public static function getMimeTypeProvider(): array
    {
        return [
            ['image/gif', 'image/gif', false],
            [['image/gif', 'video', 'text/test'], 'image/gif,video,text/test', false],
            [['image/gif', 'video', 'text/test'], ['image/gif', 'video', 'text/test'], true],
        ];
    }

    /**
     * Ensures that getMimeType() returns expected value
     *
     * @param string|string[] $mimeType
     * @param string|string[] $expected
     */
    #[DataProvider('getMimeTypeProvider')]
    public function testGetMimeType($mimeType, $expected, bool $asArray): void
    {
        $validator = new IsImage($mimeType);

        self::assertSame($expected, $validator->getMimeType($asArray));
    }

    /** @psalm-return array<array{string|string[], string, string[]}> */
    public static function setMimeTypeProvider(): array
    {
        return [
            ['image/jpeg', 'image/jpeg', ['image/jpeg']],
            ['image/gif, text/test', 'image/gif,text/test', ['image/gif', 'text/test']],
            [['video/mpeg', 'gif'], 'video/mpeg,gif', ['video/mpeg', 'gif']],
        ];
    }

    /**
     * Ensures that setMimeType() returns expected value
     *
     * @param string|string[] $mimeType
     * @param string[] $expectedAsArray
     */
    #[DataProvider('setMimeTypeProvider')]
    public function testSetMimeType($mimeType, string $expected, array $expectedAsArray): void
    {
        $validator = new IsImage('image/gif');
        $validator->setMimeType($mimeType);

        self::assertSame($expected, $validator->getMimeType());
        self::assertSame($expectedAsArray, $validator->getMimeType(true));
    }

    /**
     * Ensures that addMimeType() returns expected value
     */
    public function testAddMimeType(): void
    {
        $validator = new IsImage('image/gif');
        $validator->addMimeType('text');

        self::assertSame('image/gif,text', $validator->getMimeType());
        self::assertSame(['image/gif', 'text'], $validator->getMimeType(true));

        $validator->addMimeType('jpg, to');

        self::assertSame('image/gif,text,jpg,to', $validator->getMimeType());
        self::assertSame(['image/gif', 'text', 'jpg', 'to'], $validator->getMimeType(true));

        $validator->addMimeType(['zip', 'ti']);

        self::assertSame('image/gif,text,jpg,to,zip,ti', $validator->getMimeType());
        self::assertSame(['image/gif', 'text', 'jpg', 'to', 'zip', 'ti'], $validator->getMimeType(true));

        $validator->addMimeType('');

        self::assertSame('image/gif,text,jpg,to,zip,ti', $validator->getMimeType());
        self::assertSame(['image/gif', 'text', 'jpg', 'to', 'zip', 'ti'], $validator->getMimeType(true));
    }

    /**
     * @Laminas-8111
     */
    public function testErrorMessages(): void
    {
        $files = [
            'name'     => 'picture.jpg',
            'type'     => 'image/jpeg',
            'size'     => 200,
            'tmp_name' => __DIR__ . '/_files/picture.jpg',
            'error'    => 0,
        ];

        $validator = new IsImage('test/notype');
        $validator->enableHeaderCheck();

        self::assertFalse($validator->isValid(__DIR__ . '/_files/picture.jpg', $files));
        self::assertArrayHasKey('fileIsImageFalseType', $validator->getMessages());
    }

    /**
     * @todo Restore test branches under PHP 8.1 when https://bugs.php.net/bug.php?id=81426 is resolved
     */
    public function testOptionsAtConstructor(): void
    {
        if (! extension_loaded('fileinfo')) {
            self::markTestSkipped('This PHP Version has no finfo installed');
        }

        $magicFile = $this->getMagicMime();
        $options   = PHP_VERSION_ID >= 80100
            ? [
                'image/gif',
                'image/jpg',
                'enableHeaderCheck' => true,
            ]
            : [
                'image/gif',
                'image/jpg',
                'magicFile'         => $magicFile,
                'enableHeaderCheck' => true,
            ];

        $validator = new IsImage($options);

        if (PHP_VERSION_ID < 80100) {
            self::assertSame($magicFile, $validator->getMagicFile());
        }

        self::assertTrue($validator->getHeaderCheck());
        self::assertSame('image/gif,image/jpg', $validator->getMimeType());
    }

    public function testNonMimeOptionsAtConstructorStillSetsDefaults(): void
    {
        $validator = new IsImage([
            'enableHeaderCheck' => true,
        ]);

        self::assertNotEmpty($validator->getMimeType());
    }

    #[Group('Laminas-11258')]
    public function testLaminas11258(): void
    {
        $validator = new IsImage();

        self::assertFalse($validator->isValid(__DIR__ . '/_files/nofile.mo'));
        self::assertArrayHasKey('fileIsImageNotReadable', $validator->getMessages());
        self::assertStringContainsString('does not exist', current($validator->getMessages()));
    }
}
