<?php
/**
 * Shared helpers for setting auth-related cookies with hardened flags.
 * The wallos_login cookie carries the remember-me token; the same flags
 * are applied wherever it is written so security stays consistent.
 */

if (!function_exists('wallosIsSecureRequest')) {
    function wallosIsSecureRequest(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        return false;
    }
}

if (!function_exists('wallosAuthCookieParams')) {
    /**
     * Returns the standard option array for auth-grade cookies.
     * Sets HttpOnly + Secure (when on HTTPS) + SameSite=Strict.
     */
    function wallosAuthCookieParams(int $expires): array
    {
        return [
            'expires'  => $expires,
            'path'     => '/',
            'samesite' => 'Strict',
            'httponly' => true,
            'secure'   => wallosIsSecureRequest(),
        ];
    }
}
