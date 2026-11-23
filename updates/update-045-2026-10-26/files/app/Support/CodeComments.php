<?php

namespace App\Support;

/**
 * Pulls the learner's own comments (# and //) out of a code answer, so the
 * checker gets them as an explicit list to answer instead of a rule it can
 * skip. Quote-aware per line: a '#' inside "a #tag" string is not a comment.
 */
final class CodeComments
{
    /** @return list<string> "line N: text", in order */
    public static function extract(string $code): array
    {
        $notes = [];

        foreach (preg_split('/\R/', $code) as $i => $line) {
            $text = self::commentOf($line);
            if ($text !== null && $text !== '') {
                $notes[] = 'line '.($i + 1).': '.$text;
            }
        }

        return $notes;
    }

    private static function commentOf(string $line): ?string
    {
        $quote = null;
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $c = $line[$i];

            if ($quote !== null) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === $quote) {
                    $quote = null;
                }
            } elseif ($c === '"' || $c === "'" || $c === '`') {
                $quote = $c;
            } elseif ($c === '#') {
                return trim(substr($line, $i + 1));
            } elseif ($c === '/' && ($line[$i + 1] ?? '') === '/' && ($line[$i - 1] ?? '') !== ':') {
                return trim(substr($line, $i + 2));
            }
        }

        return null;
    }
}
