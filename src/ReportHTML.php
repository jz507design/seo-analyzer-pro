<?php

declare(strict_types=1);

/**
 * ReportHTML - Genera un reporte HTML autocontenido (inline CSS, sin CDN)
 * listo para imprimir o enviar por email. Branding JZDS.
 */
class ReportHTML
{
    private string $brandColor = '#007176';
    private string $accent = '#00b4ba';
    private string $dark = '#0a1821';

    public function buildReport(array $report): string
    {
        $url = $report['url'] ?? '';
        $score = (int)($report['score'] ?? 0);
        $sum = $report['summary'] ?? ['critical' => 0, 'warnings' => 0, 'info' => 0];
        $color = $score >= 80 ? '#008c64' : ($score >= 60 ? '#dca028' : '#c83c3c');

        $issues = $report['issues'] ?? [];
        $perf = $report['performance'] ?? [];
        $sec = $report['security'] ?? [];
        $meta = $report['meta_tags'] ?? [];
        $tech = $report['technologies'] ?? [];

        $issueHtml = '';
        foreach ($issues as $issue) {
            $type = $issue['type'] ?? 'info';
            $badge = match ($type) {
                'critical' => ['Critico', '#c83c3c'],
                'warning' => ['Advertencia', '#dca028'],
                default => ['Info', '#007176'],
            };
            $issueHtml .= '<li class="issue"><span class="badge" style="background:' . $badge[1] . '">' . $badge[0] . '</span><strong>' . htmlspecialchars($issue['category'] ?? '') . ':</strong> ' . htmlspecialchars($issue['message'] ?? '') . '</li>';
        }
        if ($issueHtml === '') {
            $issueHtml = '<li class="issue ok">Sin hallazgos relevantes.</li>';
        }

        $techHtml = '';
        foreach (array_slice($tech, 0, 10) as $t) {
            $techHtml .= '<span class="chip">' . htmlspecialchars((string)$t) . '</span>';
        }

        $sslDays = $sec['ssl']['days_left'] ?? null;

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Informe SEO - {$url}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, Helvetica, sans-serif; color:#1c2733; background:#f0f3f6; padding:24px; }
  .wrap { max-width:800px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,.08); }
  header { background:{$this->brandColor}; color:#fff; padding:28px 32px; }
  header .brand { font-size:12px; letter-spacing:2px; opacity:.85; text-transform:uppercase; }
  header h1 { font-size:22px; margin:6px 0; }
  header .url { font-size:13px; opacity:.9; word-break:break-all; }
  .score-row { display:flex; gap:16px; padding:24px 32px; border-bottom:1px solid #e5eaef; align-items:center; }
  .score-num { font-size:52px; font-weight:800; color:{$color}; line-height:1; }
  .score-grade { font-size:18px; color:{$color}; font-weight:700; }
  .hallazgos { margin-left:auto; display:flex; gap:20px; }
  .hallazgos .h { text-align:center; }
  .hallazgos .h b { display:block; font-size:24px; }
  .hallazgos .h small { font-size:11px; color:#5a6b7a; }
  section { padding:20px 32px; border-bottom:1px solid #e5eaef; }
  h2 { font-size:14px; text-transform:uppercase; letter-spacing:1px; color:{$this->brandColor}; margin-bottom:14px; border-left:4px solid {$this->brandColor}; padding-left:10px; }
  .grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px,1fr)); gap:12px; }
  .kpi { background:#f7f9fb; border-radius:8px; padding:12px; }
  .kpi b { display:block; font-size:18px; }
  .kpi small { font-size:11px; color:#5a6b7a; }
  .row { display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #f0f3f6; font-size:13px; }
  .row .v { font-weight:600; }
  .ok { color:#008c64; } .no { color:#c83c3c; }
  ul.issues { list-style:none; }
  .issue { padding:8px 0; border-bottom:1px solid #f0f3f6; font-size:13px; }
  .badge { display:inline-block; color:#fff; font-size:10px; padding:2px 8px; border-radius:10px; margin-right:8px; text-transform:uppercase; }
  .chip { display:inline-block; background:#e8f4f4; color:{$this->brandColor}; font-size:11px; padding:4px 10px; border-radius:12px; margin:3px; }
  footer { padding:20px 32px; text-align:center; font-size:12px; color:#5a6b7a; background:#0a1821; color:#9fb4c4; }
  footer b { color:#fff; }
  @media print { body { padding:0; background:#fff; } .wrap { box-shadow:none; } }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <div class="brand">JZ Design Solutions</div>
    <h1>Informe de Auditoria SEO</h1>
    <div class="url">{$url} &middot; {$this->date()}</div>
  </header>
  <div class="score-row">
    <div>
      <div class="score-num">{$score}</div>
      <div class="score-grade">/ 100</div>
    </div>
    <div class="hallazgos">
      <div class="h"><b style="color:#c83c3c">{$sum['critical']}</b><small>Criticos</small></div>
      <div class="h"><b style="color:#dca028">{$sum['warnings']}</b><small>Advertencias</small></div>
      <div class="h"><b style="color:#007176">{$sum['info']}</b><small>Info</small></div>
    </div>
  </div>
  <section>
    <h2>Metricas Clave</h2>
    <div class="grid">
      <div class="kpi"><b>{$this->val($perf['ttfb_ms'] ?? '', 'ms')}</b><small>TTFB</small></div>
      <div class="kpi"><b>{$this->val($perf['http_version'] ?? '', '')}</b><small>HTTP</small></div>
      <div class="kpi"><b>{$this->bool($sec['https'] ?? false)}</b><small>HTTPS</small></div>
      <div class="kpi"><b>{$this->val($sslDays, 'dias')}</b><small>SSL</small></div>
      <div class="kpi"><b>{$this->val($meta['word_count'] ?? '', '')}</b><small>Palabras</small></div>
      <div class="kpi"><b>{$this->val($report['images']['total'] ?? '', '')}</b><small>Imagenes</small></div>
    </div>
  </section>
  <section>
    <h2>Seguridad</h2>
    <div class="row"><span>HTTPS activo</span><span class="{$this->cls($sec['https'] ?? false)}">{$this->bool($sec['https'] ?? false)}</span></div>
    <div class="row"><span>HSTS</span><span class="{$this->cls($sec['hsts'] ?? false)}">{$this->bool(!empty($sec['hsts']))}</span></div>
    <div class="row"><span>X-Frame-Options</span><span class="{$this->cls($sec['x_frame_options'] ?? false)}">{$this->bool(!empty($sec['x_frame_options']))}</span></div>
    <div class="row"><span>X-Content-Type-Options</span><span class="{$this->cls($sec['x_content_type_options'] ?? false)}">{$this->bool(!empty($sec['x_content_type_options']))}</span></div>
    <div class="row"><span>Content-Security-Policy</span><span class="{$this->cls($sec['content_security_policy'] ?? false)}">{$this->bool(!empty($sec['content_security_policy']))}</span></div>
  </section>
  <section>
    <h2>SEO y Contenido</h2>
    <div class="row"><span>Title ({$this->len($meta['title'] ?? '')} car.)</span><span class="{$this->cls($this->titleOk($meta['title'] ?? ''))}">{$this->bool($this->titleOk($meta['title'] ?? ''))}</span></div>
    <div class="row"><span>Meta description ({$this->len($meta['description'] ?? '')} car.)</span><span class="{$this->cls(!empty($meta['description']))}">{$this->bool(!empty($meta['description']))}</span></div>
    <div class="row"><span>Canonical</span><span class="{$this->cls(!empty($meta['canonical']))}">{$this->bool(!empty($meta['canonical']))}</span></div>
    <div class="row"><span>JSON-LD (Schema.org)</span><span class="{$this->cls(!empty($meta['jsonld']))}">{$this->bool(!empty($meta['jsonld']))}</span></div>
    <div class="row"><span>sitemap.xml</span><span class="{$this->cls(!empty($meta['sitemap']))}">{$this->bool(!empty($meta['sitemap']))}</span></div>
    <div class="row"><span>robots.txt</span><span class="{$this->cls(!empty($meta['robots_file']))}">{$this->bool(!empty($meta['robots_file']))}</span></div>
    <div style="margin-top:12px">{$techHtml}</div>
  </section>
  <section>
    <h2>Diagnostico ({$this->count($issues)} hallazgos)</h2>
    <ul class="issues">{$issueHtml}</ul>
  </section>
  <footer>Generado por <b>SEO Analyzer Pro</b> &mdash; JZ Design Solutions &middot; <a href="https://jzds.me" style="color:#00b4ba">jzds.me</a> &middot; contact@jzds.me</footer>
</div>
</body>
</html>
HTML;
    }

    public function buildComparison(array $data): string
    {
        $results = $data['results'] ?? [];
        $rows = $this->comparisonRows($results);
        $winner = $data['winner_index'] ?? -1;

        $head = '<tr><th>Metrica</th>';
        foreach ($results as $i => $r) {
            $host = $this->shortHost($r['url'] ?? '');
            $tag = $i === $winner ? ' <span class="win">GANADOR</span>' : '';
            $head .= '<th>' . htmlspecialchars($host) . $tag . '</th>';
        }
        $head .= '</tr>';

        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr><td>' . htmlspecialchars($row['name']) . '</td>';
            foreach ($row['values'] as $v) {
                $body .= '<td>' . htmlspecialchars((string)$v) . '</td>';
            }
            $body .= '</tr>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Comparativa SEO</title>
<style>
  body { font-family: Arial, Helvetica, sans-serif; color:#1c2733; background:#f0f3f6; padding:24px; }
  .wrap { max-width:800px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; }
  header { background:#007176; color:#fff; padding:24px 32px; }
  header h1 { font-size:20px; } header .url { font-size:12px; opacity:.85; word-break:break-all; }
  table { width:100%; border-collapse:collapse; }
  th, td { padding:10px 14px; text-align:center; font-size:13px; border-bottom:1px solid #e5eaef; }
  th { background:#f7f9fb; color:#007176; text-transform:uppercase; font-size:11px; }
  td:first-child { text-align:left; font-weight:600; }
  .win { display:block; color:#008c64; font-size:10px; font-weight:700; }
  footer { padding:16px; text-align:center; font-size:11px; color:#5a6b7a; }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <h1>Comparativa SEO</h1>
    <div class="url">{$this->date()}</div>
  </header>
  <table><thead>{$head}</thead><tbody>{$body}</tbody></table>
  <footer>JZ Design Solutions &middot; <a href="https://jzds.me">jzds.me</a></footer>
</div>
</body>
</html>
HTML;
    }

    /* ---------- helpers ---------- */

    private function comparisonRows(array $results): array
    {
        $table = [];
        foreach ($results as $r) {
            $sec = $r['security'] ?? [];
            $meta = $r['meta'] ?? [];
            $perf = $r['performance'] ?? [];
            $table[] = [
                'Score' => (string)($r['score'] ?? 0),
                'TTFB' => isset($perf['ttfb_ms']) ? (string)round($perf['ttfb_ms']) . ' ms' : '-',
                'HTTPS' => !empty($sec['https']) ? 'SI' : 'NO',
                'HSTS' => !empty($sec['hsts']) ? 'SI' : 'NO',
                'SSL dias' => isset($sec['ssl_days_left']) ? (string)$sec['ssl_days_left'] : '-',
                'Title' => (string)($meta['title_len'] ?? 0) . ' car.',
                'Description' => (string)($meta['description_len'] ?? 0) . ' car.',
                'Canonical' => !empty($meta['has_canonical']) ? 'SI' : 'NO',
                'JSON-LD' => !empty($meta['has_jsonld']) ? 'SI' : 'NO',
                'Palabras' => (string)($meta['word_count'] ?? 0),
                'Img sin alt' => (string)($r['images']['no_alt'] ?? 0),
                'Criticos' => (string)($r['summary']['critical'] ?? 0),
                'Advertencias' => (string)($r['summary']['warnings'] ?? 0),
            ];
        }
        $out = [];
        foreach (array_keys($table[0]) as $name) {
            $out[] = ['name' => $name, 'values' => array_map(fn($row) => $row[$name], $table)];
        }
        return $out;
    }

    private function date(): string
    {
        return date('d/m/Y H:i');
    }

    private function val($v, string $unit): string
    {
        if ($v === '' || $v === null) return '-';
        return htmlspecialchars((string)$v) . ($unit !== '' ? ' ' . $unit : '');
    }

    private function bool($v): string
    {
        return $v ? 'SI' : 'NO';
    }

    private function cls($v): string
    {
        return $v ? 'ok' : 'no';
    }

    private function len(string $s): int
    {
        return mb_strlen($s);
    }

    private function titleOk(string $t): bool
    {
        $l = mb_strlen($t);
        return $l >= 30 && $l <= 65;
    }

    private function count(array $a): int
    {
        return count($a);
    }

    private function shortHost(string $url): string
    {
        $host = (string)parse_url($url, PHP_URL_HOST);
        return preg_replace('/^www\./', '', $host) ?: $url;
    }
}