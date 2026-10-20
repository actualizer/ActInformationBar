<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Unit\Service\Text;

use Act\InformationBar\Service\Text\BarTextFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BarTextFilterTest extends TestCase
{
    private BarTextFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new BarTextFilter();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function allowedTags(): iterable
    {
        yield 'bold' => ['<b>x</b>', '<b>x</b>'];
        yield 'uppercase' => ['<B>x</B>', '<b>x</b>'];
        yield 'strong' => ['<strong>x</strong>', '<strong>x</strong>'];
        yield 'italic and em' => ['<i>x</i> <em>y</em>', '<i>x</i> <em>y</em>'];
        yield 'underline and strike' => ['<u>x</u><s>y</s>', '<u>x</u><s>y</s>'];
        yield 'mark' => ['<mark>50 %</mark>', '<mark>50 %</mark>'];
    }

    #[DataProvider('allowedTags')]
    public function testAllowedTagsWork(string $input, string $expected): void
    {
        self::assertSame([$expected], $this->filter->filterLine($input)->lines);
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function visibleText(): iterable
    {
        yield 'attribute' => ['<b class="x">y</b>', '&lt;b class=&quot;x&quot;&gt;y&lt;/b&gt;', ['<b class="x">', '</b>']];
        yield 'space in tag' => ['<b >y', '&lt;b &gt;y', ['<b >']];
        yield 'br' => ['a<br>b', 'a&lt;br&gt;b', ['<br>']];
        yield 'script' => ['<script>alert(1)</script>', '&lt;script&gt;alert(1)&lt;/script&gt;', ['<script>', '</script>']];
        yield 'img' => ['<img src=x onerror=alert(1)>', '&lt;img src=x onerror=alert(1)&gt;', ['<img src=x onerror=alert(1)>']];
        yield 'link' => ['<a href="//x">y</a>', '&lt;a href=&quot;//x&quot;&gt;y&lt;/a&gt;', ['<a href="//x">', '</a>']];
        yield 'comment' => ['<!-- x -->', '&lt;!-- x --&gt;', ['<!-- x -->']];
        yield 'unfinished' => ['<b', '&lt;b', ['<b']];
        yield 'stray closer' => ['x</i>', 'x&lt;/i&gt;', ['</i>']];
    }

    /**
     * @param list<string> $shown
     */
    #[DataProvider('visibleText')]
    public function testEverythingElseStaysVisibleText(string $input, string $expected, array $shown): void
    {
        $result = $this->filter->filterLine($input);

        self::assertSame([$expected], $result->lines);
        self::assertSame($shown, $result->tagsShownAsText);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function literals(): iterable
    {
        yield 'less than' => ['Preise < 10 €', 'Preise &lt; 10 €'];
        yield 'heart' => ['Ich <3 euch', 'Ich &lt;3 euch'];
        yield 'greater than' => ['a > b', 'a &gt; b'];
        yield 'ampersand' => ['Tom & Jerry', 'Tom &amp; Jerry'];
        yield 'nbsp entity' => ['&nbsp;', '&amp;nbsp;'];
        yield 'numeric entity' => ['&#8203;', '&amp;#8203;'];
    }

    #[DataProvider('literals')]
    public function testLiteralCharactersSurviveExactly(string $input, string $expected): void
    {
        $result = $this->filter->filterLine($input);

        self::assertSame([$expected], $result->lines);
        self::assertSame([], $result->tagsShownAsText);
    }

    public function testUnclosedTagIsClosedAtLineEnd(): void
    {
        self::assertSame(['<b>x</b>'], $this->filter->filterLine('<b>x')->lines);
        self::assertSame(['<b>a</b>', 'b'], $this->filter->filterMessage("<b>a\nb")->lines);
    }

    public function testCrossedNestingIsWellFormed(): void
    {
        $result = $this->filter->filterLine('<b><i>x</b>y</i>');

        self::assertSame(['<b><i>x</i></b>y&lt;/i&gt;'], $result->lines);
        self::assertSame(['</i>'], $result->tagsShownAsText);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function randomLines(): iterable
    {
        mt_srand(20261020);
        $alphabet = ['<', '>', '/', 'b', 'i', 'mark', 'x', ' ', '&', '"', '=', "\n", '!', '-'];

        for ($n = 0; $n < 500; ++$n) {
            $line = '';
            $length = mt_rand(0, 40);
            for ($k = 0; $k < $length; ++$k) {
                $line .= $alphabet[mt_rand(0, count($alphabet) - 1)];
            }

            yield "random $n" => [$line];
        }
    }

    #[DataProvider('randomLines')]
    public function testOutputIsAlwaysWellFormed(string $input): void
    {
        $malformed = array_filter(
            $this->filter->filterLine($input)->lines,
            static fn (string $out): bool => !self::isWellFormed($out, '~<[^>]*>~')
        );

        self::assertSame([], $malformed);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invisibleChars(): iterable
    {
        $points = [
            0x0000, 0x0008, 0x000B, 0x000C, 0x001F, 0x007F, 0x009F, 0x00AD, 0x180E,
            0x200B, 0x200C, 0x200E, 0x200F, 0x2028, 0x2029, 0x202A, 0x202E, 0x2060,
            0x2066, 0x2069, 0xFEFF, 0xE0000, 0xE0041, 0xE007F,
        ];

        foreach ($points as $point) {
            yield sprintf('U+%04X', $point) => [mb_chr($point, 'UTF-8')];
        }
    }

    #[DataProvider('invisibleChars')]
    public function testInvisibleCharactersAreRemoved(string $char): void
    {
        $result = $this->filter->filterLine("a{$char}b");

        self::assertSame(['ab'], $result->lines);
        self::assertSame(1, $result->invisibleRemoved);
    }

    public function testVariationSelectorRangeStartIsKept(): void
    {
        $result = $this->filter->filterLine("a\u{FE00}b");

        self::assertSame(["a\u{FE00}b"], $result->lines);
        self::assertSame(0, $result->invisibleRemoved);
    }

    public function testTabBecomesSpace(): void
    {
        $result = $this->filter->filterLine("a\tb");

        self::assertSame(['a b'], $result->lines);
        self::assertSame(0, $result->invisibleRemoved);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function emojis(): iterable
    {
        yield 'family' => ["\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}"];
        yield 'heart' => ["\u{2764}\u{FE0F}"];
        yield 'flag' => ["\u{1F3F3}\u{FE0F}\u{200D}\u{1F308}"];
    }

    #[DataProvider('emojis')]
    public function testEmojiSequencesStayIntact(string $emoji): void
    {
        $result = $this->filter->filterLine($emoji);

        self::assertSame([$emoji], $result->lines);
        self::assertSame(0, $result->invisibleRemoved);
    }

    public function testCrLfSplitsLinesWithoutInvisibleWarning(): void
    {
        $result = $this->filter->filterMessage("a\r\nb\rc");

        self::assertSame(['a', 'b', 'c'], $result->lines);
        self::assertSame(0, $result->invisibleRemoved);
    }

    public function testPlainStorageColumnsStripTagsAndInvisibleChars(): void
    {
        self::assertSame('Los', $this->filter->cleanStorageColumn('button_text', "<b>Los\u{200B}</b>"));
        self::assertNull($this->filter->cleanStorageColumn('button_title', '<i></i>'));
        self::assertSame('<b>x</b>', $this->filter->cleanStorageColumn('message', '<b>x</b>'));
    }

    public function testSanitizeForStorageCountsNoInvisibleCharsForLineBreaks(): void
    {
        self::assertSame(0, $this->filter->sanitizeForStorage("a\r\nb")->invisibleRemoved);
        self::assertSame(0, $this->filter->sanitizeForStorage("a\rb")->invisibleRemoved);
    }

    public function testInvalidUtf8IsScrubbedNotDropped(): void
    {
        $lines = $this->filter->filterLine("a\xC3b")->lines;

        self::assertCount(1, $lines);
        self::assertStringStartsWith('a', $lines[0]);
        self::assertStringEndsWith('b', $lines[0]);
    }

    public function testBlankLinesAreDropped(): void
    {
        self::assertSame(['a', 'b'], $this->filter->filterMessage("a\n  \n\u{200B}\nb")->lines);
    }

    public function testEmptyTagPairDoesNotKeepALineAlive(): void
    {
        self::assertSame(['x'], $this->filter->filterMessage("<b></b>\nx")->lines);
    }

    public function testLineOfOnlyShownTagTextIsKept(): void
    {
        self::assertSame(['&lt;br&gt;'], $this->filter->filterLine('<br>')->lines);
        self::assertSame(['&lt;3'], $this->filter->filterLine('<3')->lines);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invisibleLooking(): iterable
    {
        yield 'nbsp' => ["\u{00A0}"];
        yield 'ideographic space' => ["\u{3000}"];
        yield 'lone zwj' => ["\u{200D}"];
        yield 'variation selector' => ["\u{FE0F}"];
    }

    #[DataProvider('invisibleLooking')]
    public function testLinesWithoutVisibleContentAreDropped(string $char): void
    {
        self::assertSame([], $this->filter->filterLine($char)->lines);
        self::assertSame(['a', 'b'], $this->filter->filterMessage("a\n{$char}\nb")->lines);
    }

    public function testCleanPlainOnlyStripsInvisibles(): void
    {
        self::assertSame('<b>Jetzt</b>', $this->filter->cleanPlain("<b>Jetzt\u{200B}</b>"));
    }

    public function testFilterLineJoinsNewlines(): void
    {
        self::assertSame(['Jetzt live'], $this->filter->filterLine("Jetzt\nlive")->lines);
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function storageTable(): iterable
    {
        yield 'allowed' => ['<b>x</b>', '<b>x</b>', []];
        yield 'uppercase unclosed' => ['<B>x', '<b>x</b>', []];
        yield 'br' => ['a<br>b', 'ab', ['<br>']];
        yield 'attribute' => ['<b class="x">y</b>', 'y', ['<b class="x">', '</b>']];
        yield 'script' => ['<script>alert(1)</script>', 'alert(1)', ['<script>', '</script>']];
        yield 'img' => ['<img src=x onerror=alert(1)>', '', ['<img src=x onerror=alert(1)>']];
        yield 'comment' => ['a<!-- hidden -->b', 'ab', ['<!-- hidden -->']];
        yield 'stray closer' => ['x</i>', 'x', ['</i>']];
        yield 'crossed' => ['<b><i>x</b>y</i>', '<b><i>x</i></b>y', ['</i>']];
        yield 'heart' => ['Ich <3 euch', 'Ich <3 euch', []];
        yield 'comparison' => ['Preise < 10 € und a > b', 'Preise < 10 € und a > b', []];
        yield 'unfinished' => ['<b', '<b', []];
        yield 'entities' => ['&nbsp; &#8203;', '&nbsp; &#8203;', []];
        yield 'tab' => ["a\tb", 'a b', []];
        yield 'tag across lines' => ["<img src=x\nonerror=alert(1)>", '', ['<img src=x' . "\n" . 'onerror=alert(1)>']];
        yield 'comment across lines' => ["a<!-- x\ny -->b", 'ab', ["<!-- x\ny -->"]];
        yield 'empty comment' => ['a<!-->b', 'ab', ['<!-->']];
        yield 'dash comment' => ['a<!--->b', 'ab', ['<!--->']];
        yield 'bogus comment' => ['a<!--x>b', 'ab', ['<!--x>']];
        yield 'crlf' => ["a\r\nb", "a\nb", []];
        yield 'blank line kept' => ["a\n\nb", "a\n\nb", []];
        yield 'reassembled open tag' => ['<<b x>b>y', '<b>y</b>', ['<b x>']];
        yield 'reassembled closer' => ['<b>x<</i>/b>', '<b>x</b>', ['</i>', '</b>']];
        yield 'reassembled script' => ['<scr<script>ipt>x', 'x', ['<script>', '<script>']];
        yield 'nested reassembly' => ['<<<i x>b x>b>z', '<b>z</b>', ['<i x>', '<b x>']];
    }

    /**
     * Inputs of the stage-2 tables, so stage 1 is checked against them too.
     *
     * @return iterable<string, array{string}>
     */
    public static function renderTableInputs(): iterable
    {
        foreach (['allowedTags', 'visibleText', 'literals'] as $provider) {
            foreach (self::$provider() as $name => $row) {
                yield "$provider: $name" => [$row[0]];
            }
        }
    }

    /**
     * @param list<string> $removed
     */
    #[DataProvider('storageTable')]
    public function testSanitizeForStorage(string $input, string $text, array $removed): void
    {
        $result = $this->filter->sanitizeForStorage($input);

        self::assertSame($text, $result->text);
        self::assertSame($removed, $result->removedFragments);
    }

    #[DataProvider('storageTable')]
    #[DataProvider('randomLines')]
    #[DataProvider('renderTableInputs')]
    public function testSanitizeIsIdempotent(string $input): void
    {
        $once = $this->filter->sanitizeForStorage($input)->text;

        self::assertSame($once, $this->filter->sanitizeForStorage($once)->text);
    }

    #[DataProvider('randomLines')]
    public function testSanitizedTextContainsOnlyAllowedTags(string $input): void
    {
        $text = $this->filter->sanitizeForStorage($input)->text;

        self::assertTrue(self::isWellFormed($text, '~<(?:!--|/?[a-zA-Z])[^<>]*>~'), $text);
    }

    public function testSanitizeCountsInvisibles(): void
    {
        $result = $this->filter->sanitizeForStorage("a\u{200B}b\u{202E}c");

        self::assertSame('abc', $result->text);
        self::assertSame(2, $result->invisibleRemoved);
    }

    #[DataProvider('storageTable')]
    public function testRenderingSanitizedTextChangesNothingVisible(string $input): void
    {
        $text = $this->filter->sanitizeForStorage($input)->text;
        $shown = $this->filter->filterMessage($text)->tagsShownAsText;

        self::assertSame($text === '<b' ? ['<b'] : [], $shown);
    }

    private static function isWellFormed(string $html, string $pattern): bool
    {
        preg_match_all($pattern, $html, $matches);
        $stack = [];

        foreach ($matches[0] as $tag) {
            if (preg_match('~^<(/?)([a-z]+)>$~', $tag, $m) !== 1 || !in_array($m[2], BarTextFilter::ALLOWED_TAGS, true)) {
                return false;
            }
            if ($m[1] === '') {
                $stack[] = $m[2];
            } elseif (array_pop($stack) !== $m[2]) {
                return false;
            }
        }

        return $stack === [];
    }
}
