<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\I18n\Translator;
use PHPUnit\Framework\TestCase;

/**
 * Unit test thuan (khong I/O ngoai doc file, khong Database/Router) cho Core\I18n\Translator -
 * Phase 13 (i18n, CMS-050). Dung fixture rieng (tests/Fixtures/lang/) thay vi resources/lang/ that
 * de test khong phu thuoc/de vo khi noi dung tu dien that thay doi sau nay.
 */
final class I18nTranslatorTest extends TestCase
{
    private const FIXTURE_LANG_PATH = __DIR__ . '/../Fixtures/lang';

    protected function tearDown(): void
    {
        Translator::setGlobalInstance(null);
    }

    public function testTranslatesKeyFromCurrentLocale(): void
    {
        $translator = new Translator(self::FIXTURE_LANG_PATH, 'en', 'vi');

        self::assertSame('Hello, :name!', $translator->translate('greeting'));
    }

    public function testInterpolatesPlaceholders(): void
    {
        $translator = new Translator(self::FIXTURE_LANG_PATH, 'en', 'vi');

        self::assertSame('Hello, An!', $translator->translate('greeting', ['name' => 'An']));
    }

    public function testInterpolatesMultiplePlaceholders(): void
    {
        $translator = new Translator(self::FIXTURE_LANG_PATH, 'vi', 'vi');

        self::assertSame('Hien thi 10 ket qua', $translator->translate('pagination.showing_count', ['count' => 10]));
    }

    public function testFallsBackToFallbackLocaleWhenKeyMissingInCurrentLocale(): void
    {
        $translator = new Translator(self::FIXTURE_LANG_PATH, 'en', 'vi');

        self::assertSame('Chi co o tieng Viet', $translator->translate('only_in_vi'));
    }

    public function testReturnsRawKeyWhenMissingInBothLocales(): void
    {
        $translator = new Translator(self::FIXTURE_LANG_PATH, 'en', 'vi');

        self::assertSame('khong.ton.tai', $translator->translate('khong.ton.tai'));
    }

    public function testSetLocaleChangesActiveLocale(): void
    {
        $translator = new Translator(self::FIXTURE_LANG_PATH, 'vi', 'vi');
        self::assertSame('vi', $translator->getLocale());

        $translator->setLocale('en');

        self::assertSame('en', $translator->getLocale());
        self::assertSame('Hello, :name!', $translator->translate('greeting'));
    }

    public function testMissingLocaleFileFallsBackGracefully(): void
    {
        $translator = new Translator(self::FIXTURE_LANG_PATH, 'fr', 'vi');

        self::assertSame('Xin chao, :name!', $translator->translate('greeting'));
    }

    public function testGlobalInstanceDefaultsToViWhenNeverSet(): void
    {
        self::assertSame('vi', Translator::globalInstance()->getLocale());
    }

    public function testHelperFunctionUsesGlobalInstance(): void
    {
        Translator::setGlobalInstance(new Translator(self::FIXTURE_LANG_PATH, 'en', 'vi'));

        self::assertSame('Hello, An!', __('greeting', ['name' => 'An']));
    }
}
