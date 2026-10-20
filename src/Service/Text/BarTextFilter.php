<?php declare(strict_types=1);

namespace Act\InformationBar\Service\Text;

use Shopware\Core\Framework\Log\Package;

/**
 * Security boundary for bar texts. Everything is treated as plain text except exact,
 * attribute-free tags from ALLOWED_TAGS. Stage 1 removes other markup before storing,
 * stage 2 escapes it when rendering.
 */
#[Package('discovery')]
final class BarTextFilter
{
    public const ALLOWED_TAGS = ['b', 'strong', 'i', 'em', 'u', 's', 'mark'];

    private const INVISIBLE = '/[\x{0000}-\x{0008}\x{000B}-\x{001F}\x{007F}-\x{009F}\x{00AD}\x{180E}'
        . '\x{200B}\x{200C}\x{200E}\x{200F}\x{2028}\x{2029}\x{202A}-\x{202E}\x{2060}\x{2066}-\x{2069}'
        . '\x{FEFF}\x{E0000}-\x{E007F}]/u';

    // Anything that opens like a tag or comment; storage removes it, rendering reports it.
    private const MARKUP_HEAD = '<(?:!--|/?[a-zA-Z])[^<>]*';
    private const STORAGE_SPLIT = '~(<!--.*?-->|' . self::MARKUP_HEAD . '>)~s';
    private const RENDER_SPLIT = '~(</?[a-zA-Z]+>)~';
    private const TAG_LIKE = '~' . self::MARKUP_HEAD . '>?~';

    /**
     * Translation storage columns that are cleaned before they reach the database.
     * The value says whether the column may keep the allowed inline markup.
     */
    public const STORAGE_COLUMNS = [
        'message' => true,
        'countdown_expired_text' => true,
        'button_text' => false,
        'button_title' => false,
    ];

    /**
     * Single cleaning rule for both the write subscriber and the stored-data cleanup.
     * Empty results are returned as null.
     */
    public function cleanStorageColumn(string $column, string $value): ?string
    {
        $clean = (self::STORAGE_COLUMNS[$column] ?? false)
            ? $this->sanitizeForStorage($value)->text
            // Plain columns: strip_tags matches what the DAL StringField does on write.
            : $this->cleanPlain(strip_tags($value));

        return $clean === '' ? null : $clean;
    }

    public function filterMessage(string $message): FilteredText
    {
        return $this->filterLines(explode("\n", $this->normalizeNewlines($message)));
    }

    public function filterLine(string $line): FilteredText
    {
        return $this->filterLines([str_replace(["\r\n", "\r", "\n"], ' ', $line)]);
    }

    public function cleanPlain(string $text): string
    {
        $removed = 0;

        return $this->clean($text, $removed) ?? '';
    }

    public function sanitizeForStorage(string $text): SanitizedText
    {
        $lines = [];
        $fragments = [];
        $invisible = 0;

        foreach (explode("\n", $this->normalizeNewlines($text)) as $line) {
            $lines[] = $this->clean($line, $invisible) ?? '';
        }
        $text = implode("\n", $lines);

        // Removing a fragment can glue a new tag together, so repeat until nothing changes.
        do {
            $previous = $text;
            $text = $this->removeForeignMarkup($text, $fragments);
            $text = implode("\n", array_map(
                function (string $line) use (&$fragments): string {
                    return $this->balance($line, false, $fragments);
                },
                explode("\n", $text)
            ));
        } while ($text !== $previous);

        return new SanitizedText($text, $fragments, $invisible);
    }

    /**
     * Runs over the whole text, so a tag or comment spanning lines is removed too.
     *
     * @param list<string> $removed
     * @param-out list<string> $removed
     */
    private function removeForeignMarkup(string $text, array &$removed): string
    {
        $parts = preg_split(self::STORAGE_SPLIT, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return '';
        }

        $out = '';
        foreach ($parts as $index => $part) {
            if ($index % 2 === 0 || $this->allowedTag($part) !== null) {
                $out .= $part;
                continue;
            }
            $removed[] = $part;
        }

        return $out;
    }

    /**
     * @return array{string, string}|null closing slash and lowercase name of an exact allowed tag
     */
    private function allowedTag(string $part): ?array
    {
        if (preg_match('~^<(/?)([a-zA-Z]+)>\z~', $part, $m) !== 1 || !in_array(strtolower($m[2]), self::ALLOWED_TAGS, true)) {
            return null;
        }

        return [$m[1], strtolower($m[2])];
    }

    /**
     * @param list<string> $rawLines
     */
    private function filterLines(array $rawLines): FilteredText
    {
        $lines = [];
        $shown = [];
        $invisible = 0;

        foreach ($rawLines as $raw) {
            $clean = $this->clean($raw, $invisible);
            if ($clean === null) {
                continue;
            }

            $html = $this->balance($clean, true, $shown);
            $visible = preg_replace(
                '/[\s\p{Z}\x{200D}\x{FE00}-\x{FE0F}]+/u',
                '',
                html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            );
            if ($visible === null || $visible === '') {
                continue;
            }

            $lines[] = $html;
        }

        return new FilteredText($lines, $shown, $invisible);
    }

    private function normalizeNewlines(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    private function clean(string $line, int &$invisible): ?string
    {
        $line = str_replace("\t", ' ', mb_scrub($line, 'UTF-8'));

        $count = 0;
        $clean = preg_replace(self::INVISIBLE, '', $line, -1, $count);
        if ($clean === null) {
            return null;
        }

        $invisible += $count;

        return $clean;
    }

    /**
     * One pass over a line. Allowed tags go through a stack so the result is always well-formed;
     * any other tag-like fragment is escaped (render) or removed (storage) and reported.
     *
     * @param list<string> $reported
     * @param-out list<string> $reported
     */
    private function balance(string $line, bool $escape, array &$reported): string
    {
        $parts = preg_split($escape ? self::RENDER_SPLIT : self::STORAGE_SPLIT, $line, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return '';
        }

        $out = '';
        $stack = [];

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                if ($escape) {
                    if (preg_match_all(self::TAG_LIKE, $part, $matches) > 0) {
                        array_push($reported, ...$matches[0]);
                    }
                    $out .= $this->escape($part);
                } else {
                    $out .= $part;
                }
                continue;
            }

            $tag = $this->allowedTag($part);
            if ($tag !== null) {
                [$slash, $name] = $tag;

                if ($slash === '') {
                    $out .= "<{$name}>";
                    $stack[] = $name;
                    continue;
                }

                if (in_array($name, $stack, true)) {
                    do {
                        $top = array_pop($stack);
                        $out .= "</{$top}>";
                    } while ($top !== $name);
                    continue;
                }
            }

            $reported[] = $part;
            $out .= $escape ? $this->escape($part) : '';
        }

        while ($stack !== []) {
            $out .= '</' . array_pop($stack) . '>';
        }

        return $out;
    }

    private function escape(string $piece): string
    {
        return htmlspecialchars($piece, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
