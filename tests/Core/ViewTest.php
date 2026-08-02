<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\View;
use Core\View\ViewException;
use Core\View\ViewNotFoundException;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        $this->view = new View(__DIR__ . '/../Fixtures/themes', 'active', 'default');
    }

    // --- Resolve View / Dot Notation ---

    public function testResolvesViewFromActiveTheme(): void
    {
        self::assertSame('Hello from active theme', trim($this->view->render('greeting')));
    }

    public function testDotNotationMapsToNestedDirectory(): void
    {
        self::assertSame('Active blog single', trim($this->view->render('blog.single')));
    }

    public function testExistsReturnsTrueForKnownViewAndFalseForUnknown(): void
    {
        self::assertTrue($this->view->exists('greeting'));
        self::assertFalse($this->view->exists('does.not.exist'));
    }

    // --- Theme Fallback ---

    public function testFallsBackToDefaultThemeWhenNotInActiveTheme(): void
    {
        self::assertSame('Only in default theme', trim($this->view->render('only_in_default')));
    }

    public function testActiveThemeTakesPrecedenceOverDefaultTheme(): void
    {
        // blog.single ton tai o CA HAI theme voi noi dung khac nhau - active phai thang.
        self::assertSame('Active blog single', trim($this->view->render('blog.single')));
    }

    // --- View Not Found ---

    public function testThrowsViewNotFoundExceptionWhenMissingInBothThemes(): void
    {
        $this->expectException(ViewNotFoundException::class);

        $this->view->render('does.not.exist');
    }

    public function testInvalidTemplateNameIsRejectedAsNotFound(): void
    {
        $this->expectException(ViewNotFoundException::class);

        // Chua ky tu khong hop le (dau /) - khong duoc phep thoat khoi thu muc views/.
        $this->view->render('../secret');
    }

    // --- Render / Data Binding ---

    public function testRenderPassesDataIntoTemplateScope(): void
    {
        self::assertSame('Hello World', trim($this->view->render('data_binding', ['name' => 'World'])));
    }

    public function testEscapeHelperEscapesHtmlSpecialChars(): void
    {
        $output = trim($this->view->render('escaping', ['value' => '<b>hi</b>']));

        self::assertSame('&lt;b&gt;hi&lt;/b&gt;|<b>hi</b>', $output);
    }

    // --- Layout / Section / Yield ---

    public function testRenderWithLayoutResolvesExtendSectionYield(): void
    {
        $output = $this->view->render('child');

        self::assertStringContainsString('LAYOUT-START|CHILD-CONTENT|LAYOUT-END', $output);
    }

    public function testSectionsAreResetBetweenIndependentRenderCalls(): void
    {
        $this->view->render('child');

        // render() thu 2, khong dung layout - khong duoc con sot section cua lan render truoc.
        $second = trim($this->view->render('greeting'));

        self::assertSame('Hello from active theme', $second);
    }

    // --- Partial / Component ---

    public function testIncludeRendersNestedPartialWithItsOwnData(): void
    {
        $output = $this->view->render('with_partial');

        self::assertStringContainsString('BEFORE-[BUTTON:Click]-AFTER', $output);
    }

    // --- Section balance safety ---

    public function testEndSectionWithoutOpenSectionThrows(): void
    {
        $this->expectException(ViewException::class);

        $this->view->endSection();
    }

    public function testNestedSectionWithoutClosingPreviousThrows(): void
    {
        $this->view->section('outer');

        try {
            $this->expectException(ViewException::class);
            $this->view->section('inner');
        } finally {
            // section('outer') da mo 1 output buffer (ob_start) chua duoc dong - phai don sach
            // vi ob_* la global stack cua ca tien trinh PHP, khong tu dong khi object bi huy.
            ob_end_clean();
        }
    }
}
