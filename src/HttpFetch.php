<?php

declare(strict_types=1);

/**
 * HttpFetch - Cliente HTTP simple para auditorias.
 * Usa stream context (sin curl) para maxima portabilidad.
 */
class HttpFetch
{
    public static function get(string $url, int $timeout = 30): array
    {
        $config = require dirname(__DIR__) . '/config/config.php';
        $timeout = $config['seo']['timeout'] ?? $timeout;
        $userAgent = $config['seo']['user_agent'] ?? 'SEO-Analyzer/1.0';

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: {$userAgent}\r\n" .
                           "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n" .
                           "Accept-Encoding: gzip, deflate\r\n",
                'timeout' => $timeout,
                'follow_location' => true,
                'max_redirects' => 5,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $start = microtime(true);
        $html = @file_get_contents($url, false, $context);
        $elapsedMs = round((microtime(true) - $start) * 1000, 1);

        $headers = $http_response_header ?? [];
        $headerMap = [];
        foreach ($headers as $header) {
            if (strpos($header, ':') !== false) {
                [$name, $value] = explode(':', $header, 2);
                $headerMap[strtolower(trim($name))] = trim($value);
            }
        }

        $compression = $headerMap['content-encoding'] ?? '';
        if ($html !== false && $compression !== '') {
            if (stripos($compression, 'gzip') !== false) {
                $decoded = @gzdecode($html);
                if ($decoded !== false) $html = $decoded;
            } elseif (stripos($compression, 'br') !== false && function_exists('brotli_uncompress')) {
                $decoded = @brotli_uncompress($html);
                if ($decoded !== false) $html = $decoded;
            }
        }

        return [
            'html' => $html,
            'http_code' => self::extractStatusCode($headers),
            'headers' => $headers,
            'header_map' => $headerMap,
            'ttfb_ms' => $elapsedMs,
            'compression' => $compression,
            'final_url' => $url,
        ];
    }

    public static function status(string $url, int $timeout = 15): int
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => $timeout,
                'follow_location' => true,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        @file_get_contents($url, false, $context);
        return self::extractStatusCode($http_response_header ?? []);
    }

    private static function extractStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\d(\.\d)?\s+(\d{3})#', $header, $m)) {
                return (int)$m[2];
            }
        }
        return 0;
    }

    public static function normalizeUrl(string $url): string
    {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        return 'https://' . $url;
    }
}