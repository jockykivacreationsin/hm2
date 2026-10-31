<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Makes the rich text staff type into the Summernote editors (symptoms, findings, notes, lab results ...) safe to print
 * with {!! !!}. It is an allow-list sanitiser built on the DOM parser, not on regular expressions:
 *
 *  - elements that can run or load code (script, style, iframe, object, embed, svg, math, form controls, ...) are
 *    removed together with their content; any other unknown element is unwrapped (its text is kept);
 *  - only the attributes listed below survive, so every on* handler goes; href/src/style values are filtered too;
 *  - links are limited to http(s), mailto, tel, relative and # targets, images to http(s), relative and data:image
 *    (not SVG) sources.
 *
 * It runs on OUTPUT, so it also protects text saved long before it existed. Use clean() for text that is stored as
 * HTML and clean($value, true) for text stored entity-encoded (the legacy app saved much of it that way).
 */
final class SafeHtml
{
    /** Elements kept (with allowed attributes only). */
    private const ALLOWED_TAGS = [
        'a', 'abbr', 'b', 'big', 'blockquote', 'br', 'caption', 'code', 'col', 'colgroup', 'dd', 'del', 'div', 'dl', 'dt',
        'em', 'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'ins', 'li', 'mark', 'ol', 'p', 'pre', 's',
        'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul',
    ];

    /** Elements removed together with everything inside them. */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'link', 'meta', 'base', 'noscript',
        'template', 'svg', 'math', 'head', 'title', 'textarea', 'select', 'option', 'button', 'input', 'form', 'audio',
        'video', 'source', 'track', 'canvas', 'portal', 'xml', 'xmp', 'plaintext', 'listing', 'noembed', 'noframes',
    ];

    /** Attributes allowed on every element. */
    private const GLOBAL_ATTRS = ['class', 'style', 'title', 'dir', 'lang', 'align'];

    /** Attributes allowed per element (on top of the global ones). */
    private const TAG_ATTRS = [
        'a'     => ['href', 'target', 'rel'],
        'img'   => ['src', 'alt', 'width', 'height'],
        'td'    => ['colspan', 'rowspan', 'valign', 'width', 'height', 'bgcolor'],
        'th'    => ['colspan', 'rowspan', 'valign', 'width', 'height', 'bgcolor'],
        'tr'    => ['valign', 'bgcolor'],
        'table' => ['border', 'cellpadding', 'cellspacing', 'width', 'bgcolor'],
        'col'   => ['span', 'width'],
        'colgroup' => ['span', 'width'],
        'font'  => ['color', 'size', 'face'],
        'ol'    => ['type', 'start'],
        'li'    => ['value'],
    ];

    /** CSS properties allowed in style="". */
    private const CSS_PROPERTIES = [
        'color', 'background-color', 'font-size', 'font-weight', 'font-style', 'font-family', 'text-align',
        'text-decoration', 'text-indent', 'line-height', 'letter-spacing', 'vertical-align', 'white-space', 'width',
        'height', 'max-width', 'list-style-type', 'border', 'border-collapse', 'border-spacing', 'border-color',
        'border-style', 'border-width', 'border-top', 'border-right', 'border-bottom', 'border-left', 'margin',
        'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding', 'padding-top', 'padding-right',
        'padding-bottom', 'padding-left',
    ];

    /**
     * @param bool $decode the text is stored entity-encoded (&lt;p&gt;...): decode it first, as the legacy views did
     */
    public static function clean(?string $html, bool $decode = false): string
    {
        $html = (string) $html;
        if ($decode) {
            $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
        }
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        // Text with no "<" or "&" cannot contain markup or an entity: nothing to parse.
        if (!preg_match('/[<&]/', $html)) {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        // The XML declaration makes libxml read the string as UTF-8; the wrapper gives us one root to walk.
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="safehtml-root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('safehtml-root');
        if (!$root) {
            return e($html);
        }
        self::cleanChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * For the PDF templates: the sanitised text reduced to the handful of tags the printouts support. strip_tags() alone
     * keeps every attribute of those tags (an inline style with url(...) would make the PDF renderer fetch it), so the
     * text is sanitised first.
     */
    public static function basic(?string $html, bool $decode = true): string
    {
        return strip_tags(self::clean($html, $decode), '<br><p><b><strong><i><em><u><s><strike><ul><ol><li>');
    }

    private static function cleanChildren(DOMNode $parent): void
    {
        // Snapshot first: children are removed / replaced while we walk.
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_COMMENT_NODE || $node->nodeType === XML_PI_NODE
                || $node->nodeType === XML_CDATA_SECTION_NODE || $node->nodeType === XML_DOCUMENT_TYPE_NODE) {
                $parent->removeChild($node);
                continue;
            }
            if (!$node instanceof DOMElement) {
                continue; // text
            }

            $tag = strtolower($node->nodeName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);
                continue;
            }

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unknown / presentational wrapper: keep what is inside, drop the element itself.
                self::cleanChildren($node);
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }

            self::cleanAttributes($node, $tag);
            self::cleanChildren($node);
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        $allowed = array_merge(self::GLOBAL_ATTRS, self::TAG_ATTRS[$tag] ?? []);

        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->nodeName);
            if (!in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->nodeName);
                continue;
            }

            $value = $attr->nodeValue ?? '';
            $ok = match ($name) {
                'href'  => self::safeUrl($value, false),
                'src'   => self::safeUrl($value, true),
                'style' => self::safeStyle($value) !== '',
                default => true,
            };
            if (!$ok) {
                $el->removeAttribute($attr->nodeName);
                continue;
            }
            if ($name === 'style') {
                $el->setAttribute('style', self::safeStyle($value));
            }
        }

        if ($tag === 'a' && $el->hasAttribute('href')) {
            // Links that open a new tab must not hand it a reference back to this page.
            $el->setAttribute('rel', 'noopener noreferrer');
            if ($el->hasAttribute('target') && !in_array(strtolower($el->getAttribute('target')), ['_blank', '_self'], true)) {
                $el->removeAttribute('target');
            }
        } elseif ($tag === 'a') {
            $el->removeAttribute('target');
        }
    }

    private static function safeUrl(string $url, bool $isImage): bool
    {
        // Browsers ignore whitespace and control characters inside a scheme ("java\tscript:"): drop them before judging.
        $probe = strtolower(preg_replace('/[\x00-\x20\x7f-\x9f]+/u', '', html_entity_decode($url, ENT_QUOTES, 'UTF-8')) ?? '');
        if ($probe === '') {
            return false;
        }
        if (!preg_match('/^([a-z][a-z0-9+.\-]*):/', $probe, $m)) {
            return true; // relative URL, "#anchor", "//host/path" and the like carry no scheme of their own
        }

        return match ($m[1]) {
            'http', 'https' => true,
            'mailto', 'tel' => !$isImage,
            'data'          => $isImage && (bool) preg_match('/^data:image\/(png|jpe?g|gif|webp|bmp);base64,[a-z0-9+\/=]+$/', $probe),
            default         => false,
        };
    }

    /** Returns the declarations that are safe, "" when none are. */
    private static function safeStyle(string $style): string
    {
        $style = html_entity_decode($style, ENT_QUOTES, 'UTF-8');
        // Comments and escapes are the usual way to smuggle "expression(" or "url(" past a filter.
        if (str_contains($style, '\\') || str_contains($style, '/*') || str_contains($style, '<') || str_contains($style, '>')) {
            return '';
        }

        $kept = [];
        foreach (explode(';', $style) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $lower = strtolower($value);
            if (!in_array($property, self::CSS_PROPERTIES, true) || $value === '') {
                continue;
            }
            if (preg_match('/url\s*\(|expression|javascript|vbscript|@import|behavior|binding|image\s*\(|\bdata:/i', $lower)) {
                continue;
            }
            $kept[] = $property . ': ' . $value;
        }

        return implode('; ', $kept);
    }
}
