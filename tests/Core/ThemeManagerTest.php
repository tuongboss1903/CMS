<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Theme\ThemeException;
use Core\ThemeManager;
use PHPUnit\Framework\TestCase;

final class ThemeManagerTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__ . '/../Fixtures/Themes';
    private const INVALID_FIXTURE_PATH = __DIR__ . '/../Fixtures/ThemesInvalid';

    public function testDiscoverFindsAllThemesInDirectory(): void
    {
        $manager = new ThemeManager(self::FIXTURE_PATH);

        $descriptors = $manager->discover();

        self::assertArrayHasKey('alpha', $descriptors);
        self::assertArrayHasKey('beta', $descriptors);
    }

    public function testDiscoverParsesManifestFieldsCorrectly(): void
    {
        $manager = new ThemeManager(self::FIXTURE_PATH);

        $descriptor = $manager->discover()['alpha'];

        self::assertSame('alpha', $descriptor->key);
        self::assertSame('Alpha Theme', $descriptor->name);
        self::assertSame('1.0.0', $descriptor->version);
        self::assertSame('screenshot.png', $descriptor->screenshot);
    }

    public function testDiscoverDoesNotMemoizeAndReReadsFilesystemEachCall(): void
    {
        $tempDir = \sys_get_temp_dir() . '/cms-themes-nomemo-' . \uniqid('', true);
        \mkdir($tempDir . '/gamma', 0775, true);
        \file_put_contents(
            $tempDir . '/gamma/theme.json',
            '{"key":"gamma","name":"Gamma","version":"1.0.0"}'
        );

        $manager = new ThemeManager($tempDir);
        $first = $manager->discover();
        self::assertCount(1, $first);

        \mkdir($tempDir . '/delta', 0775, true);
        \file_put_contents(
            $tempDir . '/delta/theme.json',
            '{"key":"delta","name":"Delta","version":"1.0.0"}'
        );

        $second = $manager->discover();

        self::assertCount(2, $second, 'discover() KHONG duoc memoize - phai thay theme moi them sau lan goi dau.');
        self::assertArrayHasKey('delta', $second);

        \unlink($tempDir . '/gamma/theme.json');
        \unlink($tempDir . '/delta/theme.json');
        \rmdir($tempDir . '/gamma');
        \rmdir($tempDir . '/delta');
        \rmdir($tempDir);
    }

    public function testFindReturnsDescriptorForExistingKey(): void
    {
        $manager = new ThemeManager(self::FIXTURE_PATH);

        $descriptor = $manager->find('alpha');

        self::assertNotNull($descriptor);
        self::assertSame('alpha', $descriptor->key);
    }

    public function testFindReturnsNullForMissingKey(): void
    {
        $manager = new ThemeManager(self::FIXTURE_PATH);

        self::assertNull($manager->find('does-not-exist'));
    }

    public function testDiscoverThrowsOnInvalidManifest(): void
    {
        $manager = new ThemeManager(self::INVALID_FIXTURE_PATH);

        $this->expectException(ThemeException::class);

        $manager->discover();
    }

    public function testScreenshotDefaultsToEmptyStringWhenMissingFromManifest(): void
    {
        $manager = new ThemeManager(self::FIXTURE_PATH);

        $descriptor = $manager->find('beta');

        self::assertNotNull($descriptor);
        self::assertSame('', $descriptor->screenshot);
    }
}
