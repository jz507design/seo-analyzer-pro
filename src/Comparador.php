<?php

declare(strict_types=1);

require_once __DIR__ . '/SEOAnalyzer.php';

/**
 * Comparador - Analiza 2-3 URLs y las compara lado a lado.
 * Para pitch de venta: "tu web vs la del competidor".
 */
class Comparador
{
    private array $urls;
    private array $results = [];

    public function __construct(array $urls)
    {
        $this->urls = array_slice(array_values(array_filter($urls)), 0, 3);
    }

    public function compare(): array
    {
        foreach ($this->urls as $url) {
            $normalized = HttpFetch::normalizeUrl($url);
            try {
                $analyzer = new SEOAnalyzer($normalized);
                $report = $analyzer->analyze();
                $this->results[] = [
                    'url' => $normalized,
                    'score' => $report['score'],
                    'summary' => $report['summary'],
                    'performance' => $report['performance'] ?? [],
                    'security' => [
                        'https' => $report['security']['https'] ?? false,
                        'hsts' => !empty($report['security']['hsts'] ?? ''),
                        'x_frame_options' => !empty($report['security']['x_frame_options'] ?? ''),
                        'ssl_days_left' => $report['security']['ssl']['days_left'] ?? null,
                    ],
                    'technologies' => $report['technologies'] ?? [],
                    'meta' => [
                        'title_len' => mb_strlen($report['meta_tags']['title'] ?? ''),
                        'description_len' => mb_strlen($report['meta_tags']['description'] ?? ''),
                        'has_canonical' => !empty($report['meta_tags']['canonical'] ?? ''),
                        'has_jsonld' => !empty($report['meta_tags']['jsonld'] ?? []),
                        'word_count' => $report['meta_tags']['word_count'] ?? 0,
                    ],
                    'images' => $report['images'] ?? [],
                    'links' => $report['links'] ?? [],
                    'issues' => $report['issues'] ?? [],
                ];
            } catch (Exception $e) {
                $this->results[] = [
                    'url' => $normalized,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $scores = array_filter(array_column($this->results, 'score'));
        $winner = null;
        if ($scores) {
            $best = max($scores);
            foreach ($this->results as $i => $r) {
                if (($r['score'] ?? 0) === $best) {
                    $winner = $i;
                    break;
                }
            }
        }

        return [
            'urls' => $this->urls,
            'results' => $this->results,
            'winner_index' => $winner,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }
}