<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Security\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new HtmlSanitizer();
    }

    public function testKeepsAllowedTagsAndText(): void
    {
        $result = $this->sanitizer->sanitize('<p>Xin <b>chao</b> the gioi</p>');

        self::assertSame('<p>Xin <b>chao</b> the gioi</p>', $result);
    }

    public function testStripsScriptTagAndItsContent(): void
    {
        $result = $this->sanitizer->sanitize('<p>Hello</p><script>alert(1)</script>');

        self::assertStringNotContainsString('script', $result);
        self::assertStringNotContainsString('alert', $result);
    }

    public function testStripsOnErrorEventHandlerAttribute(): void
    {
        $result = $this->sanitizer->sanitize('<img src="x.png" onerror="alert(1)">');

        self::assertStringNotContainsString('onerror', $result);
        self::assertStringNotContainsString('alert', $result);
        self::assertStringContainsString('src="x.png"', $result);
    }

    public function testStripsJavascriptUrlInHref(): void
    {
        $result = $this->sanitizer->sanitize('<a href="javascript:alert(1)">Click</a>');

        self::assertStringNotContainsString('javascript:', $result);
    }

    public function testKeepsSafeHttpUrlInHref(): void
    {
        $result = $this->sanitizer->sanitize('<a href="https://example.com">Link</a>');

        self::assertStringContainsString('href="https://example.com"', $result);
    }

    public function testUnwrapsDisallowedTagButKeepsInnerText(): void
    {
        $result = $this->sanitizer->sanitize('<form><p>Noi dung</p></form>');

        self::assertStringNotContainsString('<form', $result);
        self::assertStringContainsString('<p>Noi dung</p>', $result);
    }

    public function testAddsNoopenerNoreferrerForBlankTargetLink(): void
    {
        $result = $this->sanitizer->sanitize('<a href="https://example.com" target="_blank">Link</a>');

        self::assertStringContainsString('rel="noopener noreferrer"', $result);
    }

    public function testStripsIframeTagEntirely(): void
    {
        $result = $this->sanitizer->sanitize('<iframe src="https://evil.example"></iframe><p>Safe</p>');

        self::assertStringNotContainsString('iframe', $result);
        self::assertStringContainsString('<p>Safe</p>', $result);
    }

    public function testEmptyInputReturnsEmptyString(): void
    {
        self::assertSame('', $this->sanitizer->sanitize(''));
        self::assertSame('', $this->sanitizer->sanitize('   '));
    }

    public function testSanitizeContentArrayOnlySanitizesHtmlKey(): void
    {
        $result = $this->sanitizer->sanitizeContentArray([
            'html' => '<p>Hello</p><script>alert(1)</script>',
            'blocks' => ['keep-me'],
        ]);

        self::assertStringNotContainsString('script', $result['html']);
        self::assertSame(['keep-me'], $result['blocks']);
    }
}
