<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_bonifica;

defined('MOODLE_INTERNAL') || die();

#[\PHPUnit\Framework\Attributes\CoversNothing]
/**
 * Test 23: every English string exists in Spanish and Catalan, with the same placeholders.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class lang_test extends \advanced_testcase {
    /**
     * Strings of one language file.
     *
     * @param string $lang
     * @return array|null null when the pack is missing
     */
    protected static function pack(string $lang): ?array {
        $file = __DIR__ . '/../lang/' . $lang . '/local_bonifica.php';
        if (!file_exists($file)) {
            return null;
        }
        $string = [];
        include($file);
        return $string;
    }

    /**
     * Placeholders used in a string, sorted.
     *
     * @param string $text
     * @return string[]
     */
    protected static function placeholders(string $text): array {
        preg_match_all('/\{\$a(->\w+)?\}/', $text, $m);
        $out = array_values(array_unique($m[0]));
        sort($out);
        return $out;
    }

    /**
     * Languages to compare with English.
     *
     * @return array
     */
    public static function lang_provider(): array {
        return ['Spanish' => ['es'], 'Catalan' => ['ca']];
    }

    /**
     * Test 23: no key missing and no placeholder lost, so switching language never shows [[...]].
     *
     * @param string $lang
     * @dataProvider lang_provider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('lang_provider')]
    public function test_pack_complete(string $lang): void {
        $en = self::pack('en');
        $other = self::pack($lang);
        if ($other === null) {
            $this->markTestSkipped("lang/$lang/local_bonifica.php is missing");
        }
        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($other))), "Missing in $lang");
        $this->assertSame([], array_values(array_diff(array_keys($other), array_keys($en))), "Only in $lang");
        $bad = [];
        foreach ($en as $key => $text) {
            if (trim((string)$other[$key]) === '') {
                $bad[] = "$key (empty)";
            } else if (self::placeholders($text) !== self::placeholders($other[$key])) {
                $bad[] = "$key (placeholders)";
            }
        }
        $this->assertSame([], $bad, "Broken in $lang");
    }

    /**
     * Test 23: every string the code asks for exists, so no page shows [[...]].
     */
    public function test_strings_used_exist(): void {
        $en = self::pack('en');
        $root = realpath(__DIR__ . '/..');
        $used = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $path = $file->getPathname();
            if (
                preg_match('#/(tests|lang|vendor|node_modules)/#', substr($path, strlen($root)))
                || !preg_match('/\.(php|mustache)$/', $path)
            ) {
                continue;
            }
            $code = file_get_contents($path);
            preg_match_all("/get_string\\(\\s*'([a-z0-9_]+)'\\s*,\\s*(?:'local_bonifica'|self::P|\\\$p)\\b/", $code, $m);
            preg_match_all('/\{\{#str\}\}\s*([a-z0-9_]+)\s*,\s*local_bonifica\b/', $code, $t);
            foreach (array_merge($m[1], $t[1]) as $key) {
                $used[$key][] = basename($path);
            }
        }
        $this->assertNotEmpty($used);
        $missing = array_diff_key($used, $en);
        $this->assertSame([], array_map(fn($files) => implode(', ', array_unique($files)), $missing));

        $sm = get_string_manager();
        foreach (array_keys($used) as $key) {
            $this->assertTrue($sm->string_exists($key, 'local_bonifica'), $key);
        }
    }
}
