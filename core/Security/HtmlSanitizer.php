<?php

declare(strict_types=1);

namespace Core\Security;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Sanitize HTML nguoi dung nhap (Page content dang "html") truoc khi luu DB - View engine khong
 * tu escape (xem CLAUDE.md) va Modules\Page cho phep Admin nhap HTML tho qua content['html'],
 * ke ca khi hien tai chi role Admin co quyen page.create/page.update, van can lop phong thu thu 2
 * o tang luu du lieu (khong chi dua vao Authorization) - phong truong hop sau nay cap quyen nay
 * cho role thap hon (Editor/Contributor).
 *
 * Whitelist tag/attribute (khong dung thu vien ngoai nhu HTMLPurifier - du an chi co 1 dependency
 * duy nhat la psr/container, giu dung triet ly "tu viet core"). Tag/attribute khong nam trong
 * whitelist bi "unwrap" (giu lai text/con ben trong, bo the bao quanh); rieng tag nguy hiem
 * (script/style/iframe/object/embed/svg) bi xoa toan bo ca noi dung ben trong.
 */
final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's',
        'ul', 'ol', 'li', 'a', 'img',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'code', 'pre', 'span', 'div', 'hr',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
    ];

    private const STRIPPED_ENTIRELY_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'svg'];

    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Sanitize truong 'html' cua Page content (dang { "html": "<p>...</p>" }) neu co - cac dang
     * content khac (blocks, text) da duoc escape rieng luc render (xem
     * themes/default/views/pages/default.php), khong can qua sanitizer nay.
     *
     * @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    public function sanitizeContentArray(array $content): array
    {
        if (isset($content['html']) && \is_string($content['html'])) {
            $content['html'] = $this->sanitize($content['html']);
        }

        return $content;
    }

    public function sanitize(string $html): string
    {
        if (\trim($html) === '') {
            return '';
        }

        $document = new DOMDocument();
        $previousSetting = \libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
        );

        \libxml_clear_errors();
        \libxml_use_internal_errors($previousSetting);

        $root = $document->getElementsByTagName('div')->item(0);

        if (!$root instanceof DOMElement) {
            return '';
        }

        $this->cleanChildren($root);

        $result = '';

        foreach (\iterator_to_array($root->childNodes) as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (\iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if ($child instanceof DOMComment || !$child instanceof DOMElement) {
                $parent->removeChild($child);
                continue;
            }

            $tagName = \strtolower($child->tagName);

            if (\in_array($tagName, self::STRIPPED_ENTIRELY_TAGS, true)) {
                $parent->removeChild($child);
                continue;
            }

            if (!\in_array($tagName, self::ALLOWED_TAGS, true)) {
                $this->cleanChildren($child);
                $this->unwrap($parent, $child);
                continue;
            }

            $this->cleanAttributes($child, $tagName);
            $this->cleanChildren($child);
        }
    }

    private function unwrap(DOMNode $parent, DOMElement $child): void
    {
        while ($child->firstChild !== null) {
            $parent->insertBefore($child->firstChild, $child);
        }

        $parent->removeChild($child);
    }

    private function cleanAttributes(DOMElement $element, string $tagName): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tagName] ?? [];

        foreach (\iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = \strtolower($attribute->nodeName);

            if (!\in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);
                continue;
            }

            if (\in_array($name, ['href', 'src'], true) && !$this->isSafeUrl($attribute->nodeValue)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        if ($tagName === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function isSafeUrl(string $url): bool
    {
        $url = \trim((string) \preg_replace('/[\x00-\x1F\x7F]+/', '', $url));

        if ($url === '' || $url[0] === '/' || $url[0] === '#') {
            return true;
        }

        $scheme = \parse_url($url, PHP_URL_SCHEME);

        if ($scheme === null) {
            return true;
        }

        if ($scheme === false) {
            return false;
        }

        return \in_array(\strtolower($scheme), self::ALLOWED_URL_SCHEMES, true);
    }
}
