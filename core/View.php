<?php

declare(strict_types=1);

namespace Core;

use Core\View\ViewException;
use Core\View\ViewNotFoundException;
use Throwable;

/**
 * Theme Engine - class DUY NHAT trong he thong duoc phep render HTML (dung "Never: Echo HTML in
 * PHP" o Controller). Khong Business Logic - chi nhan du lieu da xu ly san qua render() va xuat
 * HTML. Khong ORM, khong compiler - include file .php thuan theo dung quyet dinh chinh thuc.
 *
 * View resolution DUNG 2 CAP CO DINH (khong danh sach N-path tong quat): active theme -> default
 * theme -> ViewNotFoundException. $activeTheme do noi khoi tao View truyen vao (tuong lai:
 * TenantManager) - View khong tu query Database de xac dinh theme dang hoat dong.
 */
final class View
{
    private const NAME_PATTERN = '/^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)*$/';

    /** @var array<string, string> */
    private array $sections = [];

    private ?string $currentSection = null;
    private ?string $layout = null;

    /** @var array<string, string> cache ket qua resolvePath() trong pham vi 1 View instance - phuc vu component/partial lap lai nhieu lan trong 1 lan render */
    private array $resolvedPathCache = [];

    /**
     * @param array<string, mixed> $globalData Phase 19 (CMS-056): du lieu merge san vao MOI
     *     render() (khong tung Controller phai tu truyen) - phuc vu diem mo rong dung chung nhu
     *     Plugin bo sung muc menu Admin qua Hook "admin.menu.items" (xem
     *     Application::registerCoreServices(), View::class factory). Key trung voi $data truyen
     *     truc tiep vao render()/include() se bi $data GHI DE (globalData chi la gia tri mac dinh).
     */
    public function __construct(
        private readonly string $themesPath,
        private readonly string $activeTheme,
        private readonly string $defaultTheme = 'default',
        private readonly array $globalData = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $this->sections = [];
        $this->layout = null;
        $startLevel = \ob_get_level();

        try {
            $content = $this->renderTemplate($template, $data);

            if ($this->layout !== null) {
                $layout = $this->layout;
                $this->layout = null;
                $content = $this->renderTemplate($layout, $data);
            }

            return $content;
        } finally {
            // Neu template loi section() khong can bang (vd goi section() 2 lan lien tiep ma
            // khong dong), don sach moi output buffer con sot de khong lam hong response HTTP
            // thuc te - buffer PHP la global stack cua ca tien trinh, khong tu don khi loi.
            while (\ob_get_level() > $startLevel) {
                \ob_end_clean();
            }

            $this->currentSection = null;
        }
    }

    public function exists(string $template): bool
    {
        try {
            $this->resolvePath($template);

            return true;
        } catch (ViewNotFoundException) {
            return false;
        }
    }

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function section(string $name): void
    {
        if ($this->currentSection !== null) {
            throw new ViewException(\sprintf(
                'Khong the mo section "%s" khi section "%s" chua duoc dong bang endSection().',
                $name,
                $this->currentSection
            ));
        }

        $this->currentSection = $name;
        \ob_start();
    }

    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new ViewException('endSection() duoc goi nhung khong co section() nao dang mo.');
        }

        $this->sections[$this->currentSection] = \ob_get_clean();
        $this->currentSection = null;
    }

    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    /** @param array<string, mixed> $data */
    public function include(string $template, array $data = []): void
    {
        echo $this->renderTemplate($template, $data);
    }

    public function e(mixed $value): string
    {
        return $this->escape($value);
    }

    public function escape(mixed $value): string
    {
        return \htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function raw(string $html): string
    {
        return $html;
    }

    /**
     * @param array<string, mixed> $data
     * @phpstan-impure Template include() co the goi $this->extend()/section() va thay doi state
     *     ($layout/$sections) - PHPStan khong thay duoc side effect nay vi no xay ra trong file
     *     .php duoc include dong, khong phai than method body.
     */
    private function renderTemplate(string $template, array $data): string
    {
        $path = $this->resolvePath($template);

        \extract([...$this->globalData, ...$data], EXTR_SKIP);

        \ob_start();

        try {
            include $path;
        } catch (Throwable $exception) {
            \ob_end_clean();

            throw $exception;
        }

        return \ob_get_clean();
    }

    private function resolvePath(string $template): string
    {
        if (isset($this->resolvedPathCache[$template])) {
            return $this->resolvedPathCache[$template];
        }

        if (\preg_match(self::NAME_PATTERN, $template) !== 1) {
            throw ViewNotFoundException::forTemplate($template, []);
        }

        $relative = \str_replace('.', DIRECTORY_SEPARATOR, $template) . '.php';
        $searched = [];

        foreach ([$this->activeTheme, $this->defaultTheme] as $theme) {
            $candidate = \rtrim($this->themesPath, '/\\') . DIRECTORY_SEPARATOR
                . $theme . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $relative;

            $searched[] = $candidate;

            if (\is_file($candidate)) {
                return $this->resolvedPathCache[$template] = $candidate;
            }
        }

        throw ViewNotFoundException::forTemplate($template, $searched);
    }
}
