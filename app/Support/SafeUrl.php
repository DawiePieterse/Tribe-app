<?php

namespace App\Support;

/**
 * Tribe fetches a few addresses people type in (a recipe page, a calendar link). The server must not
 * be talked into fetching something on its own network instead, so only public http(s) hosts pass.
 */
final class SafeUrl
{
    public static function isPublicHttp(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $port = $parts['port'] ?? null;
        if ($port !== null && ! in_array($port, [80, 443, 8080, 8443], true)) {
            return false;
        }

        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Guzzle options: redirects followed only to other public addresses, and the first request pinned
     * to the address that was checked, so a name can't resolve somewhere else a moment later.
     *
     * @return array<string, mixed>
     */
    public static function httpOptions(?string $url = null): array
    {
        $pin = [];
        $parts = $url !== null ? parse_url($url) : false;

        if (is_array($parts) && ! empty($parts['host']) && ! filter_var(trim($parts['host'], '[]'), FILTER_VALIDATE_IP)) {
            $ip = gethostbyname($parts['host']);
            $port = $parts['port'] ?? (strtolower($parts['scheme'] ?? '') === 'http' ? 80 : 443);
            if ($ip !== $parts['host']) {
                $pin = ['curl' => [CURLOPT_RESOLVE => [$parts['host'].':'.$port.':'.$ip]]];
            }
        }

        return $pin + [
            'allow_redirects' => [
                'max' => 3,
                'protocols' => ['http', 'https'],
                'on_redirect' => function ($request, $response, $uri): void {
                    if (! self::isPublicHttp((string) $uri)) {
                        throw new \RuntimeException('Redirect to a non-public address.');
                    }
                },
            ],
        ];
    }
}
