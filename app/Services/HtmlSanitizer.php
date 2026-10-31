<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitizes rich text HTML written by instructors so it can be safely rendered to students.
 * Plain text / markdown without any HTML tags is returned untouched.
 */
class HtmlSanitizer
{
    /** Elements that are kept. Anything else is unwrapped (children kept) or dropped if dangerous. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'sub', 'sup',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
        'a', 'img', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    /** Elements removed together with all their content. */
    private const DROP_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'form', 'input',
        'button', 'textarea', 'select', 'svg', 'math', 'base', 'frame', 'frameset', 'applet',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        if (! preg_match('/<\s*[a-z!\/]/i', $html)) {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div id="sanitizer-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('sanitizer-root');
        if (! $root) {
            return strip_tags($html);
        }

        $this->cleanChildren($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    private function cleanChildren(DOMNode $parent): void
    {
        // Copy to an array because the live node list changes while we mutate it.
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                if ($child->nodeType === XML_COMMENT_NODE) {
                    $parent->removeChild($child);
                }

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_TAGS, true)) {
                $parent->removeChild($child);

                continue;
            }

            $this->cleanChildren($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);

                continue;
            }

            $this->cleanAttributes($child, $tag);
        }
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if (in_array($name, ['href', 'src'], true) && ! $this->isSafeUrl($attribute->value, $name === 'src')) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($tag === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('rel', 'noopener noreferrer');
            $element->setAttribute('target', '_blank');
        }
    }

    private function isSafeUrl(string $url, bool $isImage): bool
    {
        // Strip control characters / whitespace tricks such as "java\tscript:".
        $normalized = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', $url) ?? '');

        if ($normalized === '') {
            return false;
        }

        if (str_starts_with($normalized, '//')) {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/', $normalized, $m)) {
            $allowed = $isImage ? ['http', 'https'] : ['http', 'https', 'mailto', 'tel'];

            return in_array($m[1], $allowed, true);
        }

        // Relative URLs (e.g. /storage/editor-images/x.png) are fine.
        return true;
    }
}
