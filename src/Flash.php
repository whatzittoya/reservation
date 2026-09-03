<?php

declare(strict_types=1);

namespace App;

/**
 * One-shot message carried across a redirect in the session.
 *
 * Used by cloud sync: the local save redirects, and the outcome of the push —
 * which may have failed while the save succeeded — has to survive that hop.
 */
final class Flash
{
    private const KEY = 'flash';

    /** @param 'ok'|'err' $type */
    public static function set(string $type, string $text): void
    {
        $_SESSION[self::KEY] = ['type' => $type, 'text' => $text];
    }

    /**
     * Read and clear.
     *
     * @return array{type:string,text:string}|null
     */
    public static function take(): ?array
    {
        $flash = $_SESSION[self::KEY] ?? null;
        unset($_SESSION[self::KEY]);

        return is_array($flash) && isset($flash['type'], $flash['text'])
            ? ['type' => (string) $flash['type'], 'text' => (string) $flash['text']]
            : null;
    }
}
