<?php

declare(strict_types=1);

/**
 * Marketing site (kaamfit.ddev.site) vs job portal ( /dashboard or portal.kaamfit.ddev.site ).
 */
final class Site
{
    public static function host(): string
    {
        return strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    }

    public static function isPortalHost(): bool
    {
        $host = self::host();
        return str_starts_with($host, 'portal.');
    }

    /** Shared parent cookie domain for kaamfit + portal.kaamfit on DDEV. */
    public static function sessionCookieDomain(): ?string
    {
        $host = self::host();
        if (preg_match('/(^|\.)kaamfit\.ddev\.site$/', $host)) {
            return '.kaamfit.ddev.site';
        }
        return null;
    }

    public static function marketingBaseUrl(): string
    {
        $env = trim((string) (getenv('MNK_PUBLIC_URL') ?: ''));
        if ($env !== '') {
            return rtrim($env, '/');
        }
        $host = self::host();
        if ($host !== '') {
            if (self::isPortalHost()) {
                $host = preg_replace('/^portal\./', '', $host) ?? $host;
            }
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
            return ($https ? 'https' : 'http') . '://' . $host;
        }
        return 'https://kaamfit.ddev.site';
    }

    public static function portalBaseUrl(): string
    {
        $env = trim((string) (getenv('MNK_PORTAL_URL') ?: ''));
        if ($env !== '') {
            return rtrim($env, '/');
        }
        $host = self::host();
        if ($host !== '' && str_contains($host, 'kaamfit.ddev.site')) {
            return 'https://portal.kaamfit.ddev.site';
        }
        // Path-based fallback when subdomain is not configured.
        return self::marketingBaseUrl() . '/dashboard';
    }

    /**
     * Where to send the user after login/register.
     * Default: /dashboard on this host. Set MNK_PORTAL_URL to force the portal subdomain.
     */
    public static function portalHomeUrl(): string
    {
        if (self::isPortalHost()) {
            return '/';
        }
        $env = trim((string) (getenv('MNK_PORTAL_URL') ?: ''));
        if ($env !== '') {
            return rtrim($env, '/') . '/';
        }
        return '/dashboard';
    }

    /** Same-host path for portal home (nav links when already on portal or path-only mode). */
    public static function portalHomePath(): string
    {
        return self::isPortalHost() ? '/' : '/dashboard';
    }

    public static function marketingHomeUrl(): string
    {
        if (!self::isPortalHost()) {
            return '/';
        }
        return self::marketingBaseUrl() . '/';
    }

    /** Safe internal next URL after login (paths only), defaulting to portal home. */
    public static function sanitizeNext(string $next): string
    {
        $next = trim($next);
        if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
            return self::portalHomePath();
        }
        // Never treat marketing root as the app home after login.
        if ($next === '/') {
            return self::portalHomePath();
        }
        return $next;
    }
}
