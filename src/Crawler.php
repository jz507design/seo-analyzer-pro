<?php

declare(strict_types=1);

require_once __DIR__ . '/HttpFetch.php';
require_once __DIR__ . '/SEOAnalyzer.php';

/**
 * Crawler - Auditoria multi-pagina de un sitio web.
 * Extrae enlaces internos desde la home y analiza hasta N paginas.
 */
class Crawler
{
    private string $startUrl;
    private int $maxPages;
    private int $timeout;
    private array $visited = [];
    private array $queue = [];
    private array $pages = [];
    private array $issuesByType = ['critical' => 0, 'warning' => 0, 'info' => 0];

    public function __construct(string $url, int $maxPages = 10, int $timeout = 30)
    {
        $this->startUrl = HttpFetch::normalizeUrl($url);
        $this->maxPages = max(1, min(50, $maxPages));
        $this->timeout = $timeout;
    }

    public function crawl(): array
    {
        $host = strtolower((string)parse_url($this->startUrl, PHP_URL_HOST));

        $this->queue[] = $this->startUrl;

        while (!empty($this->queue) && count($this->visited) < $this->maxPages) {
            $url = array_shift($this->queue);
            $key = md5($url);
            if (isset($this->visited[$key])) {
                continue;
            }
            $this->visited[$key] = true;

            $start = microtime(true);
            $resp = HttpFetch::get($url, $this->timeout);
            $elapsed = round((microtime(true) - $start) * 1000);

            if ($resp['html'] === false) {
                $this->pages[] = [
                    'url' => $url,
                    'error' => 'No se pudo acceder',
                    'http_code' => $resp['http_code'],
                ];
                continue;
            }

            $analyzer = new SEOAnalyzer($url);
            $analyzer->setRawHTML($resp['html'], $resp['header_map'], $resp['ttfb_ms'], $resp['compression']);
            $report = $analyzer->analyze();

            // Limitar contenido pesado que no aporta al resumen
            $page = [
                'url' => $url,
                'http_code' => $resp['http_code'],
                'score' => $report['score'],
                'ttfb_ms' => $elapsed,
                'title' => $report['meta_tags']['title'] ?? '',
                'issues_count' => count($report['issues']),
                'issues' => array_map(fn($i) => ['type' => $i['type'], 'category' => $i['category'], 'message' => $i['message']], $report['issues']),
                'performance' => $report['performance'] ?? [],
                'technologies' => $report['technologies'] ?? [],
            ];
            $this->pages[] = $page;

            foreach (['critical', 'warning', 'info'] as $t) {
                $this->issuesByType[$t] += $report['summary'][$t] ?? 0;
            }

            // Encontrar enlaces internos para seguir crawleando
            if (count($this->visited) < $this->maxPages) {
                foreach ($this->extractInternalLinks($resp['html'], $host) as $link) {
                    $this->queue[] = $link;
                }
            }
        }

        $scores = array_column(array_filter($this->pages, fn($p) => isset($p['score'])), 'score');
        $avg = $scores ? (int)round(array_sum($scores) / count($scores)) : 0;

        return [
            'start_url' => $this->startUrl,
            'host' => $host,
            'pages_analyzed' => count($this->pages),
            'pages_requested' => count($this->visited),
            'average_score' => $avg,
            'max_score' => $scores ? max($scores) : 0,
            'min_score' => $scores ? min($scores) : 0,
            'issues_total' => $this->issuesByType,
            'pages' => $this->pages,
        ];
    }

    private function extractInternalLinks(string $html, string $host): array
    {
        $links = [];
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//a[@href]') as $node) {
            $href = trim($node->getAttribute('href'));
            if ($href === '' || $href === '#') continue;
            if (preg_match('#^(javascript:|mailto:|tel:|#)', $href)) continue;

            $abs = $this->absoluteUrl($href);
            if (!$abs) continue;

            $parsed = parse_url($abs);
            if (strtolower($parsed['host'] ?? '') !== $host) continue;

            // Evitar archivos que no son paginas
            if (preg_match('#\.(jpg|jpeg|png|gif|webp|svg|css|js|pdf|zip|xml|txt)$#i', $parsed['path'] ?? '')) continue;

            $links[md5($abs)] = $abs;
        }

        // Limitar a maxPages*3 candidatos para no explotar la cola
        return array_slice(array_values($links), 0, $this->maxPages * 3);
    }

    private function absoluteUrl(string $href): ?string
    {
        $base = $this->startUrl;
        if (str_starts_with($href, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            $parts = parse_url($base);
            $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
            return $origin . $href;
        }
        if (filter_var($href, FILTER_VALIDATE_URL)) {
            return $href;
        }
        return null;
    }
}