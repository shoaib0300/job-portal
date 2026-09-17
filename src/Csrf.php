<?php

declare(strict_types=1);

/**
 * Session CSRF tokens for form and AJAX endpoints.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . App::e(self::token()) . '">';
    }

    public static function meta(): string
    {
        return '<meta name="csrf-token" content="' . App::e(self::token()) . '">';
    }

    public static function validate(?string $token): bool
    {
        $expected = self::token();
        if ($token === null || $token === '' || $expected === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    /** Read token from POST body, JSON body key, or X-CSRF-Token header. */
    public static function requestToken(): ?string
    {
        if (isset($_POST['_csrf']) && is_string($_POST['_csrf'])) {
            return $_POST['_csrf'];
        }
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (is_string($header) && $header !== '') {
            return $header;
        }

        return null;
    }

    public static function requireValid(?string $token = null): void
    {
        $token ??= self::requestToken();
        if (!self::validate($token)) {
            throw new InvalidArgumentException('Invalid CSRF token.');
        }
    }
}
