<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace local_custompage\local\helpers;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Page designs: import an HTML file (for example one made with ChatGPT), keep its CSS to one page, and make its HTML safe.
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class design {
    /** @var string[] Elements removed with their content. */
    private const REMOVE = ['script', 'style', 'link', 'meta', 'base', 'title', 'noscript', 'object', 'embed', 'applet', 'frame',
        'frameset', 'set', 'animate', 'animatetransform', 'animatemotion', 'handler', 'listener'];

    /**
     * CSS class that wraps a page's content and scopes its design CSS.
     *
     * @param int $pageid
     * @return string
     */
    public static function scope_class(int $pageid): string {
        return 'local-custompage-design-' . $pageid;
    }

    /**
     * Turn a design's CSS into CSS that only applies inside one page.
     *
     * Rules for html, body and :root apply to the page's wrapper instead, so a design written as a whole web page
     * still works without restyling the rest of the site.
     *
     * @param string $css
     * @param int $pageid
     * @return string Compiled CSS
     * @throws \moodle_exception When the CSS cannot be understood
     */
    public static function scope_css(string $css, int $pageid): string {
        if (trim($css) === '') {
            return '';
        }
        // Remove comments, then @charset and @import (imports must be first in a stylesheet; keep font imports).
        $css = preg_replace('~/\*.*?\*/~s', '', $css);
        $css = preg_replace('~@charset[^;]*;~i', '', $css);
        $imports = [];
        // Sass directives that write rules outside the page's wrapper, or load other files, are not allowed.
        if (preg_match('~@(at-root|use|forward)\b~i', $css)) {
            throw new \moodle_exception('designcsserror', 'local_custompage', '', '@at-root, @use and @forward');
        }
        $css = preg_replace_callback('~@import\b[^;]*;~i', static function (array $m) use (&$imports): string {
            if (preg_match('~(https://(?:fonts\.googleapis\.com|fonts\.bunny\.net)/[^"\')\s;]+)~i', $m[0], $url)) {
                $imports[] = '@import url("' . $url[1] . '");';
            }
            return '';
        }, $css);

        // Minified CSS often leaves out the last semicolon in a block. The SCSS compiler reads a custom property
        // (--name) up to the next semicolon, so add the missing ones or later rules are swallowed into its value.
        $css = preg_replace('/([^;{}\s])(\s*)\}/', '$1;$2}', $css);

        // Point html, body and :root selectors at the wrapper.
        $css = preg_replace_callback('~(^|[{}])([^{}@]+)\{~', static function (array $m): string {
            $selectors = array_map(static function (string $selector): string {
                $selector = preg_replace('/^(\s*)((html|:root)\s+body|html|body|:root)(?=$|[\s.#:\[>+~,])/i', '$1&', $selector);
                return $selector;
            }, explode(',', $m[2]));
            return $m[1] . implode(',', $selectors) . '{';
        }, $css);

        // Themes often colour every heading; a design expects headings to follow its own text colour unless it says
        // otherwise. This reset comes first, so the design's own heading rules still win.
        $reset = "h1, h2, h3, h4, h5, h6 { color: inherit; font-family: inherit; }\n";
        $scss = '.' . self::scope_class($pageid) . " {\n" . $reset . $css . "\n}";
        try {
            $compiler = new \core_scss();
            $compiled = $compiler->compile($scss);
        } catch (\Throwable $e) {
            // The compiler's message can include server paths: show only the line it stopped at.
            $where = preg_match('~line:? (\d+)~i', $e->getMessage(), $line)
                ? 'check near line ' . max(1, (int) $line[1] - 2) : 'check for a missing } or ;';
            throw new \moodle_exception('designcsserror', 'local_custompage', '', $where);
        }
        // Never let the stylesheet close the style tag it is printed in.
        return str_ireplace('</', '<\/', implode("\n", $imports) . "\n" . $compiled);
    }

    /**
     * Read a design file: its CSS (from style tags and font links) and the content of its body.
     *
     * @param string $html
     * @return array [string $css, string $bodyhtml]
     */
    public static function import_html(string $html): array {
        $html = self::unwrap_chat_answer($html);
        $doc = self::load($html);
        $css = [];
        foreach ($doc->getElementsByTagName('link') as $link) {
            $href = (string) $link->getAttribute('href');
            if (
                stripos((string) $link->getAttribute('rel'), 'stylesheet') !== false
                    && preg_match('~^https://(?:fonts\.googleapis\.com|fonts\.bunny\.net)/~i', $href)
            ) {
                $css[] = '@import url("' . $href . '");';
            }
        }
        foreach ($doc->getElementsByTagName('style') as $style) {
            $css[] = $style->textContent;
        }
        $body = $doc->getElementsByTagName('body')->item(0);
        $content = $body ? self::inner_html($body) : '';
        return [trim(implode("\n", $css)), self::sanitise_html($content)];
    }

    /**
     * Take the code out of a chat answer saved as text: its fenced html and css code blocks, without the explanation around them.
     *
     * Text that is already plain HTML is returned unchanged.
     *
     * @param string $text
     * @return string
     */
    private static function unwrap_chat_answer(string $text): string {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (!preg_match_all('/\x60{3}[ \t]*([A-Za-z]*)[^\n]*\n(.*?)\x60{3}/s', $text, $blocks, PREG_SET_ORDER)) {
            return $text;
        }
        $css = [];
        $markup = [];
        foreach ($blocks as [, $language, $code]) {
            $language = strtolower($language);
            if ($language === 'css' || $language === 'scss') {
                $css[] = $code;
            } else if (in_array($language, ['', 'html', 'htm', 'xml'], true) && strpos($code, '<') !== false) {
                $markup[] = $code;
            }
        }
        if (!$markup && !$css) {
            return $text;
        }
        $styles = $css ? '<style>' . implode("\n", $css) . '</style>' : '';
        $markup = implode("\n", $markup);
        // Put separate CSS where import_html() looks for it: in the head of a full page, or before a fragment.
        if ($styles && stripos($markup, '</head>') !== false) {
            return str_ireplace('</head>', $styles . '</head>', $markup);
        }
        return $styles . $markup;
    }

    /**
     * Remove anything that could run code from page HTML: scripts, event handlers, javascript: links, embedded objects.
     *
     * Page content is written only by page managers (a capability flagged RISK_XSS), and it is shown without Moodle's
     * HTML cleaning so designs keep their structure; this removes the parts that could run code.
     *
     * @param string $html
     * @return string
     */
    public static function sanitise_html(string $html): string {
        if (trim($html) === '') {
            return '';
        }
        $doc = self::load('<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>');
        $xpath = new DOMXPath($doc);
        foreach (self::REMOVE as $tag) {
            foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $node) {
                $node->parentNode->removeChild($node);
            }
        }
        foreach (iterator_to_array($xpath->query('//*')) as $element) {
            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = strtolower(preg_replace('~[\s\x00-\x1f]+~', '', (string) $attribute->nodeValue));
                if (
                    str_starts_with($name, 'on') || $name === 'srcdoc' || $name === 'formaction'
                        || (in_array($name, ['href', 'src', 'action', 'xlink:href', 'data', 'poster', 'to', 'from', 'values', 'by'], true)
                            && (str_starts_with($value, 'javascript:') || str_starts_with($value, 'vbscript:')
                                || (str_starts_with($value, 'data:') && !str_starts_with($value, 'data:image/'))))
                ) {
                    $element->removeAttribute($attribute->nodeName);
                }
            }
            if (strtolower($element->nodeName) === 'iframe') {
                // Scripts plus same-origin would let a frame showing this site lift its own sandbox, so only frames
                // from other sites (videos, forms, maps) keep their own origin.
                $sandbox = 'allow-scripts allow-popups allow-presentation';
                if (self::is_other_site((string) $element->getAttribute('src'))) {
                    $sandbox .= ' allow-same-origin';
                }
                $element->setAttribute('sandbox', $sandbox);
            }
            if (strtolower($element->nodeName) === 'form') {
                $element->removeAttribute('action');
            }
        }
        $body = $doc->getElementsByTagName('body')->item(0);
        return $body ? trim(self::inner_html($body)) : '';
    }

    /**
     * Whether a frame address points at another website (not this Moodle site).
     *
     * @param string $src
     * @return bool
     */
    private static function is_other_site(string $src): bool {
        global $CFG;
        $src = trim($src);
        if (str_starts_with($src, '//')) {
            $src = 'https:' . $src;
        }
        if (!preg_match('~^https?://~i', $src)) {
            return false;
        }
        $host = strtolower((string) parse_url($src, PHP_URL_HOST));
        $own = strtolower((string) parse_url($CFG->wwwroot, PHP_URL_HOST));
        return $host !== '' && $host !== $own;
    }

    /**
     * Parse HTML as UTF-8.
     *
     * @param string $html
     * @return DOMDocument
     */
    private static function load(string $html): DOMDocument {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        // The XML declaration makes libxml read the markup as UTF-8.
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $doc;
    }

    /**
     * HTML of an element's children.
     *
     * @param DOMElement $element
     * @return string
     */
    private static function inner_html(DOMElement $element): string {
        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }
        return $html;
    }
}
