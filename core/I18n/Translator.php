<?php

declare(strict_types=1);

namespace Core\I18n;

/**
 * Doc tu dien static tu resources/lang/{locale}.php (mang phang key => string, KHONG dot-notation
 * long nhau ben trong file - giu don gian dung MVP Phase 13, CMS-050). Khong tim thay key o locale
 * hien tai -> fallback locale ('vi' mac dinh) -> tra nguyen key goc neu ca 2 deu khong co.
 *
 * Instance nay tu no KHONG static/global - nhung co 1 static holder rieng
 * (setGlobalInstance()/globalInstance()) DUY NHAT de phuc vu helper toan cuc __() (core/helpers.php)
 * goi duoc tu View template (template khong co Dependency Injection, chi co $this la View instance).
 * Day la ngoai le co chu dich, cung mo hinh voi Session da co lien quan superglobal $_SESSION duoc
 * co lap vao dung 1 class - LocaleDetectionMiddleware la noi DUY NHAT duoc goi setGlobalInstance()
 * voi locale that cua tung request.
 */
final class Translator
{
    private const DEFAULT_FALLBACK_LOCALE = 'vi';

    private static ?self $global = null;

    /** @var array<string, array<string, string>> */
    private array $dictionaries = [];

    public function __construct(
        private readonly string $langPath,
        private string $locale,
        private readonly string $fallbackLocale = self::DEFAULT_FALLBACK_LOCALE,
    ) {
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getFallbackLocale(): string
    {
        return $this->fallbackLocale;
    }

    /**
     * @param array<string, scalar> $replace Thay the placeholder dang ":ten" trong chuoi da dich
     *  (vd ['name' => 'An'] thay ":name" -> "An").
     */
    public function translate(string $key, array $replace = []): string
    {
        $value = $this->lookup($this->locale, $key)
            ?? $this->lookup($this->fallbackLocale, $key)
            ?? $key;

        return $this->interpolate($value, $replace);
    }

    private function lookup(string $locale, string $key): ?string
    {
        $dictionary = $this->loadDictionary($locale);

        return isset($dictionary[$key]) ? (string) $dictionary[$key] : null;
    }

    /** @return array<string, string> */
    private function loadDictionary(string $locale): array
    {
        if (isset($this->dictionaries[$locale])) {
            return $this->dictionaries[$locale];
        }

        $file = \rtrim($this->langPath, '/\\') . DIRECTORY_SEPARATOR . $locale . '.php';
        $dictionary = \is_file($file) ? require $file : [];

        return $this->dictionaries[$locale] = \is_array($dictionary) ? $dictionary : [];
    }

    /** @param array<string, scalar> $replace */
    private function interpolate(string $value, array $replace): string
    {
        if ($replace === []) {
            return $value;
        }

        $pairs = [];

        foreach ($replace as $placeholder => $replacement) {
            $pairs[':' . $placeholder] = (string) $replacement;
        }

        return \strtr($value, $pairs);
    }

    /**
     * Diem truy cap global DUY NHAT (xem docblock class) - danh rieng cho helper __(). KHONG goi
     * truc tiep tu Controller/Service khac, luon uu tien Constructor Injection binh thuong o do.
     */
    public static function globalInstance(): self
    {
        if (self::$global === null) {
            self::$global = new self(
                \dirname(__DIR__, 2) . '/resources/lang',
                self::DEFAULT_FALLBACK_LOCALE,
                self::DEFAULT_FALLBACK_LOCALE
            );
        }

        return self::$global;
    }

    /** LocaleDetectionMiddleware goi voi locale that cua request; truyen null de reset (vd giua cac test). */
    public static function setGlobalInstance(?self $translator): void
    {
        self::$global = $translator;
    }
}
