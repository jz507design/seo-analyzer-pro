#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * SEO Analyzer Pro - CLI / TUI
 * Uso:
 *   php seo-analyzer.php analyze <url> [--api-key KEY] [--ai] [--json]
 *   php seo-analyzer.php compare <url1> <url2> [<url3>]
 *   php seo-analyzer.php crawl <url> [--max-pages N]
 *   php seo-analyzer.php report <url> [--pdf|--html] [--out file]
 *   php seo-analyzer.php history [--limit N]
 *   php seo-analyzer.php monitor <url> [--interval S] [--times N]
 *   php seo-analyzer.php serve [--port 8099]
 *   php seo-analyzer.php tui
 *
 * La API key se pasa por --api-key, variable de entorno DEEPSEEK_API_KEY,
 * o se pide por prompt. NUNCA se guarda en disco.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);

require_once __DIR__ . '/src/SEOAnalyzer.php';
require_once __DIR__ . '/src/HttpFetch.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Crawler.php';
require_once __DIR__ . '/src/Comparador.php';
require_once __DIR__ . '/src/ReportPDF.php';
require_once __DIR__ . '/src/ReportHTML.php';
require_once __DIR__ . '/src/DeepSeekAPI.php';

class CLI
{
    private const VERSION = '2.0.0';
    private ?Database $db = null;

    public function run(array $argv): int
    {
        $args = array_slice($argv, 1);
        $cmd = $args[0] ?? 'help';

        return match ($cmd) {
            'analyze' => $this->analyze(array_slice($args, 1)),
            'compare' => $this->compare(array_slice($args, 1)),
            'crawl' => $this->crawl(array_slice($args, 1)),
            'report' => $this->report(array_slice($args, 1)),
            'history' => $this->history(array_slice($args, 1)),
            'monitor' => $this->monitor(array_slice($args, 1)),
            'serve' => $this->serve(array_slice($args, 1)),
            'tui' => $this->tui(),
            'help', '--help', '-h' => $this->help(),
            default => $this->error("Comando desconocido: {$cmd}"),
        };
    }

    /* ================= helpers ================= */

    private function db(): Database
    {
        if ($this->db === null) {
            $this->db = new Database();
        }
        return $this->db;
    }

    private function parseOpts(array $args, array $flags = []): array
    {
        // Flags que toman un valor en el siguiente argumento (--flag valor o --flag=valor)
        $valueFlags = ['api-key', 'out', 'max-pages', 'limit', 'delete', 'interval', 'times', 'port', 'host'];
        $opts = [];
        $pos = [];
        $i = 0;
        $n = count($args);
        while ($i < $n) {
            $arg = $args[$i];
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $key = $parts[0];
                if (in_array($key, $flags, true)) {
                    if (in_array($key, $valueFlags, true)) {
                        if (isset($parts[1])) {
                            $opts[$key] = $parts[1];
                        } elseif ($i + 1 < $n) {
                            $opts[$key] = $args[$i + 1];
                            $i++;
                        } else {
                            $opts[$key] = true;
                        }
                    } else {
                        $opts[$key] = $parts[1] ?? true;
                    }
                }
            } else {
                $pos[] = $arg;
            }
            $i++;
        }
        return [$pos, $opts];
    }

    private function apiKey(array $opts): ?string
    {
        if (!empty($opts['api-key']) && $opts['api-key'] !== true) {
            return (string)$opts['api-key'];
        }
        $env = getenv('DEEPSEEK_API_KEY');
        if ($env) {
            return $env;
        }
        return null;
    }

    private function askApiKey(): string
    {
        if (function_exists('readline')) {
            $key = readline('DeepSeek API key (solo para esta sesion): ');
        } else {
            echo 'DeepSeek API key (solo para esta sesion): ';
            $key = trim(fgets(STDIN) ?: '');
        }
        return trim($key);
    }

    private function out(string $msg, string $color = 'white'): void
    {
        $colors = [
            'green' => "\033[32m", 'red' => "\033[31m", 'yellow' => "\033[33m",
            'cyan' => "\033[36m", 'bold' => "\033[1m", 'dim' => "\033[2m",
            'white' => "\033[0m",
        ];
        $code = $colors[$color] ?? "\033[0m";
        echo $code . $msg . "\033[0m" . PHP_EOL;
    }

    private function error(string $msg): int
    {
        $this->out("ERROR: {$msg}", 'red');
        return 1;
    }

    private function scoreColor(int $score): string
    {
        return $score >= 80 ? 'green' : ($score >= 60 ? 'yellow' : 'red');
    }

    /* ================= comandos ================= */

    private function analyze(array $args): int
    {
        [$pos, $opts] = $this->parseOpts($args, ['api-key', 'ai', 'json']);
        if (!$pos) {
            return $this->error("Uso: analyze <url> [--api-key KEY] [--ai] [--json]");
        }
        $url = HttpFetch::normalizeUrl($pos[0]);

        $this->out("Analizando {$url} ...", 'cyan');
        $analyzer = new SEOAnalyzer($url);
        $report = $analyzer->analyze();
        $this->db()->save($url, $report);

        if (!empty($opts['json'])) {
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            return 0;
        }

        $this->printReport($report);

        if (!empty($opts['ai'])) {
            $key = $this->apiKey($opts) ?? $this->askApiKey();
            if ($key === '') {
                $this->out('Sin API key, omitiendo analisis IA.', 'dim');
                return 0;
            }
            $this->aiSummary($report, $key);
        }
        return 0;
    }

    private function printReport(array $report): void
    {
        $score = (int)($report['score'] ?? 0);
        $sum = $report['summary'] ?? [];
        $perf = $report['performance'] ?? [];
        $sec = $report['security'] ?? [];

        $this->out('========================================', 'bold');
        $this->out("SEO SCORE: {$score}/100  [{$this->grade($score)}]", $this->scoreColor($score));
        $this->out("  Criticos: {$sum['critical']} | Advertencias: {$sum['warnings']} | Info: {$sum['info']}", 'dim');
        $this->out('========================================', 'bold');

        $this->out("TTFB: " . round($perf['ttfb_ms'] ?? 0) . " ms | HTTP: " . strtoupper($perf['http_version'] ?? '-'), 'cyan');
        $this->out("HTTPS: " . ($sec['https'] ? 'SI' : 'NO') . " | SSL dias: " . ($sec['ssl']['days_left'] ?? '-') . " | HSTS: " . (!empty($sec['hsts']) ? 'SI' : 'NO'), 'cyan');

        $issues = $report['issues'] ?? [];
        foreach (array_slice($issues, 0, 15) as $i) {
            $tag = match ($i['type'] ?? 'info') {
                'critical' => '[!]',
                'warning' => '[~]',
                default => '[i]',
            };
            $color = match ($i['type'] ?? 'info') {
                'critical' => 'red',
                'warning' => 'yellow',
                default => 'dim',
            };
            $this->out("  {$tag} {$i['category']}: {$i['message']}", $color);
        }
        if (count($issues) > 15) {
            $this->out("  ... y " . (count($issues) - 15) . " mas. Usa --json para el detalle.", 'dim');
        }
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A+', $score >= 80 => 'A', $score >= 70 => 'B',
            $score >= 60 => 'C', $score >= 50 => 'D', default => 'F',
        };
    }

    private function aiSummary(array $report, string $key): void
    {
        $this->out('Consultando analisis IA...', 'cyan');
        $issues = $report['issues'] ?? [];
        $critical = array_values(array_filter($issues, fn($i) => ($i['type'] ?? '') === 'critical'));
        $critical = array_slice($critical, 0, 5);

        $prompt = "Eres un auditor SEO. Responde en espanol en 3 secciones: " .
            "1) DIAGNOSTICO (2-3 lineas, puntos fuertes y debiles), " .
            "2) PRIORIDAD 1 (las 3 acciones mas importantes con impacto), " .
            "3) PRIORIDAD 2 (acciones secundarias). " .
            "Puntuacion: {$report['score']}/100. " .
            "Problemas criticos: " . json_encode($critical, JSON_UNESCAPED_UNICODE) . ".";

        try {
            $ai = new DeepSeekAPI(['api_key' => $key]);
            $result = $ai->analyzeSEO($report);
            echo PHP_EOL . $this->formatAiResult($result) . PHP_EOL;
        } catch (Exception $e) {
            $this->out('Error en IA: ' . $e->getMessage(), 'red');
        }
    }

    private function formatAiResult(array $result): string
    {
        $out = '';
        if (!empty($result['resumen'])) $out .= $result['resumen'] . PHP_EOL . PHP_EOL;
        if (!empty($result['fortalezas'])) {
            $out .= 'FORTALEZAS:' . PHP_EOL;
            foreach ($result['fortalezas'] as $f) $out .= '  + ' . $f . PHP_EOL;
            $out .= PHP_EOL;
        }
        if (!empty($result['problemas_criticos'])) {
            $out .= 'PROBLEMAS CRITICOS:' . PHP_EOL;
            foreach ($result['problemas_criticos'] as $p) $out .= '  ! ' . $p . PHP_EOL;
            $out .= PHP_EOL;
        }
        if (!empty($result['acciones_prioritarias'])) {
            $out .= 'ACCIONES PRIORITARIAS:' . PHP_EOL;
            foreach ($result['acciones_prioritarias'] as $a) $out .= '  * ' . $a . PHP_EOL;
        }
        if (!empty($result['mejoras_sugeridas'])) {
            $out .= PHP_EOL . 'MEJORAS SUGERIDAS:' . PHP_EOL;
            foreach ($result['mejoras_sugeridas'] as $m) $out .= '  - ' . $m . PHP_EOL;
        }
        return trim($out);
    }
    private function compare(array $args): int
    {
        [$pos, $opts] = $this->parseOpts($args, ['api-key', 'pdf', 'html', 'out']);
        if (count($pos) < 2) {
            return $this->error("Uso: compare <url1> <url2> [url3] [--pdf|--html] [--out file]");
        }
        $urls = array_slice($pos, 0, 3);
        $this->out('Comparando: ' . implode(' vs ', $urls), 'cyan');

        $comparador = new Comparador($urls);
        $data = $comparador->compare();

        foreach ($data['results'] as $i => $r) {
            $score = $r['score'] ?? null;
            if ($score === null) {
                $this->out("  {$i}. {$r['url']} -> ERROR", 'red');
                continue;
            }
            $winner = $data['winner_index'] === $i ? '  <<< GANADOR' : '';
            $this->out("  {$i}. " . $this->shortHost($r['url']) . " -> {$score}/100" . $winner, $this->scoreColor($score));
        }

        if (!empty($opts['pdf']) || !empty($opts['html'])) {
            $out = $opts['out'] ?? 'comparativa.' . (!empty($opts['pdf']) ? 'pdf' : 'html');
            $this->generateComparison($data, $out, !empty($opts['pdf']));
        }
        return 0;
    }

    private function crawl(array $args): int
    {
        [$pos, $opts] = $this->parseOpts($args, ['max-pages', 'json']);
        if (!$pos) {
            return $this->error("Uso: crawl <url> [--max-pages N] [--json]");
        }
        $maxPages = (int)($opts['max-pages'] ?? 10);
        $this->out("Crawleando {$pos[0]} (max {$maxPages} paginas)...", 'cyan');

        $crawler = new Crawler($pos[0], $maxPages);
        $result = $crawler->crawl();

        if (!empty($opts['json'])) {
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            return 0;
        }

        $this->out("Sitio: {$result['host']} | Paginas analizadas: {$result['pages_analyzed']}", 'bold');
        $this->out("Score promedio: {$result['average_score']} | Max: {$result['max_score']} | Min: {$result['min_score']}", $this->scoreColor($result['average_score']));
        $this->out("Issues: C={$result['issues_total']['critical']} W={$result['issues_total']['warning']} I={$result['issues_total']['info']}", 'dim');

        foreach ($result['pages'] as $p) {
            $status = isset($p['error']) ? "ERROR" : "{$p['score']}/100";
            $color = isset($p['error']) ? 'red' : $this->scoreColor($p['score'] ?? 0);
            $this->out("  [{$p['http_code']}] {$p['url']} -> {$status}", $color);
        }
        return 0;
    }

    private function report(array $args): int
    {
        [$pos, $opts] = $this->parseOpts($args, ['pdf', 'html', 'out']);
        if (!$pos) {
            return $this->error("Uso: report <url> [--pdf|--html] [--out file]");
        }
        $url = HttpFetch::normalizeUrl($pos[0]);
        $this->out("Generando reporte para {$url}...", 'cyan');

        $analyzer = new SEOAnalyzer($url);
        $report = $analyzer->analyze();
        $this->db()->save($url, $report);

        if (!empty($opts['pdf'])) {
            $out = $opts['out'] ?? 'reporte-' . date('Ymd-His') . '.pdf';
            $pdf = new ReportPDF('portrait');
            $pdf->buildReport($report);
            $pdf->output($out);
            $this->out("PDF generado: {$out}", 'green');
        } elseif (!empty($opts['html'])) {
            $out = $opts['out'] ?? 'reporte-' . date('Ymd-His') . '.html';
            $html = new ReportHTML();
            file_put_contents($out, $html->buildReport($report));
            $this->out("HTML generado: {$out}", 'green');
        } else {
            $this->printReport($report);
        }
        return 0;
    }

    private function history(array $args): int
    {
        [$pos, $opts] = $this->parseOpts($args, ['limit', 'delete']);
        $limit = (int)($opts['limit'] ?? 10);

        if (!empty($opts['delete'])) {
            $id = (int)$opts['delete'];
            $this->db()->delete($id);
            $this->out("Auditoria #{$id} eliminada.", 'green');
            return 0;
        }

        $rows = $this->db()->recent($limit);
        if (!$rows) {
            $this->out('Sin historial. Ejecuta: php seo-analyzer.php analyze <url>', 'dim');
            return 0;
        }
        $this->out('Historial de auditorias:', 'bold');
        foreach ($rows as $r) {
            $this->out("  #{$r['id']} [{$r['created_at']}] {$r['url']} -> {$r['score']}/100 (C:{$r['critical']} W:{$r['warnings']})", $this->scoreColor((int)$r['score']));
        }
        return 0;
    }

    private function monitor(array $args): int
    {
        [$pos, $opts] = $this->parseOpts($args, ['interval', 'times', 'json']);
        if (!$pos) {
            return $this->error("Uso: monitor <url> [--interval S] [--times N]");
        }
        $url = HttpFetch::normalizeUrl($pos[0]);
        $interval = max(5, (int)($opts['interval'] ?? 60));
        $times = (int)($opts['times'] ?? 0);

        $this->out("Monitoreando {$url} cada {$interval}s" . ($times ? " x{$times}" : '') . " (Ctrl+C para salir)", 'cyan');
        $count = 0;
        while (true) {
            $count++;
            $start = microtime(true);
            $code = HttpFetch::status($url, 15);
            $elapsed = round((microtime(true) - $start) * 1000);
            $now = date('H:i:s');
            $state = $code >= 200 && $code < 400 ? 'UP' : 'DOWN';
            $color = $state === 'UP' ? 'green' : 'red';
            $this->out("[{$now}] {$state} HTTP {$code} ({$elapsed} ms) -> {$url}", $color);
            if ($times && $count >= $times) break;
            sleep($interval);
        }
        return 0;
    }

    private function serve(array $args): int
    {
        [$pos, $opts] = $this->parseOpts($args, ['port', 'host']);
        $port = (int)($opts['port'] ?? 8099);
        $host = (string)($opts['host'] ?? '127.0.0.1');
        $docroot = dirname(__DIR__) . '/public';
        if (!is_dir($docroot)) {
            mkdir($docroot, 0775, true);
        }
        $this->out("Servidor web: http://{$host}:{$port}/  (Ctrl+C para salir)", 'green');
        $this->out("Document root: {$docroot}", 'dim');

        $cmd = sprintf('"%s" -S %s:%d -t "%s" "%s"', PHP_BINARY, $host, $port, $docroot, dirname(__DIR__) . '/bin/router.php');
        passthru($cmd);
        return 0;
    }

    private function tui(): int
    {
        $this->out('SEO Analyzer Pro v' . self::VERSION, 'bold');
        $this->out('Menu interactivo (escribe el numero y Enter)', 'dim');
        $this->out('');

        while (true) {
            $this->out('1. Analizar una URL', 'cyan');
            $this->out('2. Comparar sitios', 'cyan');
            $this->out('3. Crawl completo', 'cyan');
            $this->out('4. Generar reporte PDF', 'cyan');
            $this->out('5. Historial', 'cyan');
            $this->out('6. Monitor (uptime)', 'cyan');
            $this->out('7. Iniciar servidor web', 'cyan');
            $this->out('0. Salir', 'dim');
            echo '> ';
            $choice = trim(fgets(STDIN) ?: '');

            switch ($choice) {
                case '1':
                    echo 'URL: ';
                    $url = trim(fgets(STDIN) ?: '');
                    if ($url) $this->analyze([$url]);
                    break;
                case '2':
                    echo 'URLs separadas por espacio (2-3): ';
                    $urls = preg_split('/\s+/', trim(fgets(STDIN) ?: ''), -1, PREG_SPLIT_NO_EMPTY);
                    if (count($urls) >= 2) $this->compare($urls);
                    break;
                case '3':
                    echo 'URL: ';
                    $url = trim(fgets(STDIN) ?: '');
                    if ($url) $this->crawl([$url]);
                    break;
                case '4':
                    echo 'URL: ';
                    $url = trim(fgets(STDIN) ?: '');
                    if ($url) $this->report([$url, '--pdf']);
                    break;
                case '5':
                    $this->history([]);
                    break;
                case '6':
                    echo 'URL: ';
                    $url = trim(fgets(STDIN) ?: '');
                    if ($url) $this->monitor([$url, '--interval=30']);
                    break;
                case '7':
                    $this->serve([]);
                    break;
                case '0':
                    $this->out('Adios.', 'dim');
                    return 0;
            }
            $this->out('');
        }
    }

    private function generateComparison(array $data, string $out, bool $pdfMode): void
    {
        if ($pdfMode) {
            $pdf = new ReportPDF('portrait');
            $pdf->buildComparison($data);
            $pdf->output($out);
        } else {
            $html = new ReportHTML();
            file_put_contents($out, $html->buildComparison($data));
        }
        $this->out("Comparativa generada: {$out}", 'green');
    }

    private function shortHost(string $url): string
    {
        $host = (string)parse_url($url, PHP_URL_HOST);
        return preg_replace('/^www\./', '', $host) ?: $url;
    }

    private function help(): int
    {
        $this->out('SEO Analyzer Pro v' . self::VERSION . ' - JZ Design Solutions', 'bold');
        $this->out('Uso: php seo-analyzer.php <comando> [opciones]', 'cyan');
        $this->out('');
        $this->out('Comandos:', 'bold');
        $this->out('  analyze <url> [--api-key KEY] [--ai] [--json]');
        $this->out('  compare <url1> <url2> [url3] [--pdf|--html] [--out file]');
        $this->out('  crawl <url> [--max-pages N] [--json]');
        $this->out('  report <url> [--pdf|--html] [--out file]');
        $this->out('  history [--limit N] [--delete ID]');
        $this->out('  monitor <url> [--interval S] [--times N]');
        $this->out('  serve [--port 8099] [--host 127.0.0.1]');
        $this->out('  tui');
        $this->out('');
        $this->out('La API key se pasa por --api-key o env DEEPSEEK_API_KEY.', 'dim');
        $this->out('NUNCA se guarda en disco.', 'dim');
        return 0;
    }
}

$cli = new CLI();
exit($cli->run($argv));