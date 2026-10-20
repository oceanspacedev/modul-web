<?php

namespace App\Support;

class SafeRedirect
{
    /**
     * Return the target only when it points back to this application, so
     * user-supplied "redirect" values can never send people to another site.
     */
    public static function sanitize(mixed $target): ?string
    {
        if (! is_string($target) || $target === '') {
            return null;
        }

        // Relative paths are fine, protocol-relative ("//evil.test") and backslash tricks are not.
        if (str_starts_with($target, '/')) {
            return str_starts_with($target, '//') || str_contains($target, '\\') ? null : $target;
        }

        $host = parse_url($target, PHP_URL_HOST);
        $scheme = parse_url($target, PHP_URL_SCHEME);

        if (! in_array($scheme, ['http', 'https'], true) || $host !== request()->getHost()) {
            return null;
        }

        return $target;
    }

    public static function to(mixed $target, string $fallback): string
    {
        return self::sanitize($target) ?? $fallback;
    }
}
