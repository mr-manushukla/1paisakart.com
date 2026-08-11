<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the whole site private behind one shared password.
 *
 * Applied to the web AND api groups, so the gate can't be walked around by
 * calling the API directly.
 *
 * The unlock is remembered in a signed cookie rather than the session: API
 * requests only get a session when Sanctum recognises them as coming from a
 * stateful domain, so a session-backed gate 401s every API call the moment that
 * detection doesn't fire — which looks exactly like a broken site.
 *
 * No SITE_PASSWORD set (the default) = no gate at all.
 */
class SitePassword
{
    public const COOKIE = 'site_unlock';

    /** Days an unlock lasts before the password is asked for again. */
    private const DAYS = 30;

    /**
     * Reachable while the site is gated: the health check, the unlock endpoint
     * itself, and the deploy hook — which carries its own token.
     */
    private const OPEN = ['up', '__unlock', '__deploy'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::enabled() || $request->is(...self::OPEN) || self::unlocked($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'This site is private.'], 401);
        }

        return response()->view('site-password', [
            'redirect' => self::safePath($request->path()),
            'error' => $request->query('locked') !== null,
        ], 401);
    }

    public static function enabled(): bool
    {
        return (string) config('app.site_password') !== '';
    }

    public static function unlocked(Request $request): bool
    {
        return self::enabled() && hash_equals(self::token(), (string) $request->cookie(self::COOKIE));
    }

    /**
     * The pass itself. Derived from APP_KEY so it can't be forged, and from the
     * password so that changing the password revokes every pass already issued.
     */
    public static function token(): string
    {
        return hash_hmac('sha256', 'site-unlock', config('app.key').'|'.config('app.site_password'));
    }

    /** Constant-time check — a shared gate is still a secret. */
    public static function matches(?string $attempt): bool
    {
        return self::enabled() && hash_equals((string) config('app.site_password'), (string) $attempt);
    }

    /** HttpOnly so page scripts can't read the pass; Lax so normal navigation keeps it. */
    public static function cookie(Request $request): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(self::COOKIE, self::token(), self::DAYS * 24 * 60, null, null, $request->secure(), true, false, 'Lax');
    }

    /**
     * Only ever redirect back within this site. Take the path component and
     * nothing else, so any host in the input is dropped rather than honoured.
     * Backslashes are folded first — browsers treat "\\evil.com" as "//evil.com".
     */
    public static function safePath(?string $path): string
    {
        $path = str_replace('\\', '/', (string) $path);

        return '/'.ltrim((string) parse_url($path, PHP_URL_PATH), '/');
    }
}
