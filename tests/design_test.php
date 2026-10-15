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

namespace local_custompage;

use advanced_testcase;
use local_custompage\local\helpers\design;

/**
 * Page designs: CSS scoping, importing an HTML file, and removing anything that could run code.
 *
 * @package    local_custompage
 * @category   test
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_custompage\local\helpers\design
 */
final class design_test extends advanced_testcase {
    /**
     * Every rule ends up inside the page's wrapper class; html, body and :root point at the wrapper.
     */
    public function test_scope_css(): void {
        $css = ":root{--accent:#2563eb}\nbody{margin:0;font-family:Inter}\nhtml body .hero{padding:2rem}\n"
            . ".card h3{color:var(--accent)}\n@media (max-width:600px){.grid{display:block}}";
        $out = design::scope_css($css, 7);
        $scope = '.local-custompage-design-7';

        $this->assertStringContainsString("$scope {", $out);
        $this->assertStringContainsString('--accent:#2563eb', $out);
        $this->assertStringContainsString("$scope .hero", $out);
        $this->assertStringContainsString("$scope .card h3", $out);
        $this->assertMatchesRegularExpression('~@media \(max-width: ?600px\)\s*\{\s*' . preg_quote($scope) . ' \.grid~', $out);
        $this->assertStringNotContainsString('body', $out);
        $this->assertStringNotContainsString(':root', $out);
        // Every selector outside @-rules starts with the wrapper.
        preg_match_all('~(?:^|\})\s*([^{}@]+)\{~', preg_replace('~@media[^{]+\{~', '', $out), $m);
        foreach ($m[1] as $selectors) {
            foreach (explode(',', $selectors) as $selector) {
                if (trim($selector) !== '') {
                    $this->assertStringStartsWith($scope, trim($selector));
                }
            }
        }
    }

    /**
     * A custom property without a trailing semicolon does not swallow the next rule.
     */
    public function test_scope_css_missing_semicolon(): void {
        $out = design::scope_css(':root{--a:#fff;--b:#000} .x{color:red}', 1);
        $this->assertStringContainsString('.local-custompage-design-1 .x', $out);
        $this->assertStringContainsString('color: red', $out);
    }

    /**
     * Font imports are kept, other imports and @charset are dropped, and CSS cannot close its style tag.
     */
    public function test_scope_css_imports_and_style_tag(): void {
        $css = "@charset \"utf-8\";\n@import url('https://fonts.googleapis.com/css2?family=Inter');\n"
            . "@import url('https://evil.example/x.css');\n.a{content:\"</style><script>\"}";
        $out = design::scope_css($css, 2);
        $this->assertStringStartsWith('@import url("https://fonts.googleapis.com/css2?family=Inter");', ltrim($out));
        $this->assertStringNotContainsString('evil.example', $out);
        $this->assertStringNotContainsString('@charset', $out);
        $this->assertStringNotContainsString('</', $out);
    }

    /**
     * Empty CSS compiles to nothing; CSS that cannot be read gives a clear error.
     */
    public function test_scope_css_empty_and_invalid(): void {
        $this->assertSame('', design::scope_css("  \n", 3));
        $this->expectException(\moodle_exception::class);
        design::scope_css('.a { color: red; ', 3);
    }

    /**
     * Importing a design file keeps its styles, font links and body, and removes scripts.
     */
    public function test_import_html(): void {
        $html = '<!doctype html><html><head><title>T</title>'
            . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter">'
            . '<link rel="stylesheet" href="https://evil.example/x.css">'
            . '<style>.hub{color:#123}</style></head>'
            . '<body><section class="hub"><h2>Résumé ✓</h2><button onclick="go()">Go</button></section>'
            . '<script>alert(1)</script></body></html>';
        [$css, $body] = design::import_html($html);
        $this->assertStringContainsString('@import url("https://fonts.googleapis.com/css2?family=Inter");', $css);
        $this->assertStringContainsString('.hub{color:#123}', $css);
        $this->assertStringNotContainsString('evil.example', $css);
        $this->assertStringContainsString('<section class="hub">', $body);
        $this->assertStringContainsString('Résumé ✓', $body);
        $this->assertStringContainsString('<button>Go</button>', $body);
        $this->assertStringNotContainsString('script', $body);
        $this->assertStringNotContainsString('onclick', $body);
    }

    /**
     * Anything that could run code is removed; layout and safe links stay.
     */
    public function test_sanitise_html(): void {
        $html = '<div class="a" id="top" style="color:red"><a href="javascript:alert(1)">x</a>'
            . '<a href="https://moodle.org" target="_blank">ok</a><img src="data:image/png;base64,AA" onerror="x()">'
            . '<a href=" JaVa&#10;script:alert(1)">y</a><iframe src="https://www.youtube.com/embed/x"></iframe>'
            . '<iframe srcdoc="<script>x</script>"></iframe><object data="x.swf"></object>'
            . '<form action="https://evil.example"><button formaction="https://evil.example">b</button></form>'
            . '<a href="data:text/html,<script>x</script>">z</a></div>';
        $out = design::sanitise_html($html);
        $this->assertStringContainsString('class="a"', $out);
        $this->assertStringContainsString('id="top"', $out);
        $this->assertStringContainsString('style="color:red"', $out);
        $this->assertStringContainsString('href="https://moodle.org"', $out);
        $this->assertStringContainsString('src="data:image/png;base64,AA"', $out);
        $this->assertStringContainsString('sandbox="', $out);
        foreach (['javascript', 'onerror', 'srcdoc', '<object', 'evil.example', 'data:text'] as $bad) {
            $this->assertStringNotContainsStringIgnoringCase($bad, $out);
        }
        $this->assertSame('', design::sanitise_html(''));
    }

    /**
     * A chat answer saved as a .txt file: the code blocks are used and the explanation around them is dropped.
     */
    public function test_import_chat_answer_text(): void {
        $f = str_repeat(chr(96), 3); // A Markdown code fence.
        $answer = "Here is your page!\n\n" . $f . "css\n.tbp-hero { color: #123456; }\n" . $f
            . "\n\nAnd the HTML:\n\n" . $f . "html\n"
            . "<div class=\"tbp-hero\"><h2>Managing behaviour</h2></div>\n" . $f . "\n\nPaste it into Moodle.";
        [$css, $body] = design::import_html($answer);
        $this->assertStringContainsString('.tbp-hero { color: #123456; }', $css);
        $this->assertStringContainsString('<div class="tbp-hero"><h2>Managing behaviour</h2></div>', $body);
        $this->assertStringNotContainsString('Here is your page', $body);
        $this->assertStringNotContainsString($f, $body);

        // A whole page in one block, with its style in the head.
        $answer = $f . "html\n<!DOCTYPE html><html><head><style>.a{color:red}</style></head>"
            . "<body><p class=\"a\">Hi</p></body></html>\n" . $f;
        [$css, $body] = design::import_html($answer);
        $this->assertSame('.a{color:red}', $css);
        $this->assertSame('<p class="a">Hi</p>', trim($body));

        // Plain HTML is untouched.
        [$css, $body] = design::import_html('<p>Plain</p>');
        $this->assertSame('', $css);
        $this->assertSame('<p>Plain</p>', trim($body));
    }
}
