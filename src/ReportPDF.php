<?php

declare(strict_types=1);

/**
 * ReportPDF - Generador de PDF en PHP puro (sin librerias externas).
 * Emite PDF 1.4 con fuentes estandar (Type1), multipagina y colores de marca JZDS.
 * Sistema de coordenadas: origen arriba-izquierda, Y crece hacia abajo.
 */
class ReportPDF
{
    private array $pages = [];
    private int $pageCount = 0;
    private float $pageWidth;
    private float $pageHeight;
    private float $margin = 40.0;
    private float $y = 40.0;
    private array $fontUsed = [];
    private array $brand = [
        'primary' => [0, 113, 118],
        'accent' => [0, 180, 186],
        'dark' => [10, 24, 33],
        'muted' => [110, 120, 130],
        'light' => [235, 240, 244],
        'white' => [255, 255, 255],
        'red' => [200, 60, 60],
        'amber' => [220, 160, 40],
        'green' => [0, 140, 100],
    ];

    public function __construct(string $orientation = 'portrait')
    {
        if ($orientation === 'landscape') {
            $this->pageWidth = 842.0;
            $this->pageHeight = 595.0;
        } else {
            $this->pageWidth = 595.0;
            $this->pageHeight = 842.0;
        }
        $this->addPage();
    }

    /* ================= bajo nivel ================= */

    /**
     * Convierte una Y del sistema "arriba-izquierda" (como se usa en el resto
     * de la clase) a la coordenada PDF estandar (origen abajo-izquierda).
     * Antes se usaba una CTM con reflexion (1 0 0 -1 0 H cm) que volteaba
     * el texto y dejaba bandas en posiciones invertidas.
     */
    private function py(float $y): float
    {
        return $this->pageHeight - $y;
    }

    private function emit(string $s): void
    {
        $this->pages[$this->pageCount - 1] .= $s;
    }

    private function rgb(array $c): string
    {
        // Espacio final obligatorio: sin el, "rg" se concatena con el siguiente
        // operador (ej. "rg0.00 750.00") y el parser PDF rompe el stream.
        return sprintf('%.3f %.3f %.3f rg ', $c[0] / 255, $c[1] / 255, $c[2] / 255);
    }

    private function sanitize(string $s): string
    {
        $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N', 'ü' => 'u', 'Ü' => 'U',
            '¿' => '?', '¡' => '!', '“' => '"', '”' => '"', '‘' => "'", '’' => "'",
        ];
        return strtr($s, $map);
    }

    private function esc(string $s): string
    {
        $s = $this->sanitize($s);
        $s = str_replace(["\r", "\n"], ' ', $s);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    public function addPage(): void
    {
        // Coordenadas PDF estandar: origen abajo-izquierda. La conversion de
        // Y se hace en cada primitiva via py() (evita CTM reflectante).
        $this->pages[] = '';
        $this->pageCount = count($this->pages);
        $this->y = $this->margin;
    }

    public function yPos(): float
    {
        return $this->y;
    }

    public function moveTo(float $y): void
    {
        $this->y = $y;
    }

    public function ensureSpace(float $needed): void
    {
        if ($this->y + $needed > $this->pageHeight - $this->margin) {
            $this->addPage();
        }
    }

    public function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->emit($this->rgb($rgb));
        // En PDF "re" usa la esquina inferior-izquierda: la Y del sistema
        // arriba-izquierda es la esquina superior -> convertir con py() - h.
        $this->emit(sprintf("%.2f %.2f %.2f %.2f re f\n", $x, $this->py($y + $h), $w, $h));
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $rgb, float $width = 1): void
    {
        $this->emit($this->rgb($rgb));
        $this->emit(sprintf("%.2f w\n", $width));
        $this->emit(sprintf("%.2f %.2f m %.2f %.2f l S\n", $x1, $this->py($y1), $x2, $this->py($y2)));
    }

    public function text(float $x, float $y, string $str, int $size = 10, ?array $rgb = null, string $family = 'helvetica'): void
    {
        $rgb = $rgb ?? $this->brand['dark'];
        $key = $family . '|' . $size;
        if (!isset($this->fontUsed[$key])) {
            $this->fontUsed[$key] = count($this->fontUsed) + 1;
        }
        $this->emit("BT\n/F{$this->fontUsed[$key]} {$size} Tf\n");
        $this->emit($this->rgb($rgb));
        // En PDF "Td" posiciona la linea base del texto; el texto crece hacia
        // arriba, por eso convertimos la Y superior al espacio PDF.
        $this->emit(sprintf("%.2f %.2f Td\n", $x, $this->py($y + $size)));
        $this->emit('(' . $this->esc($str) . ") Tj\nET\n");
    }

    public function textRight(float $xRight, float $y, string $str, int $size = 10, ?array $rgb = null, string $family = 'helvetica'): void
    {
        // Alinea el FINAL del texto contra xRight (el texto crece hacia la
        // izquierda); sin esto los textos del margen derecho se salian del
        // canvas y quedaban cortados ("https://ex").
        $this->text($xRight - $this->textWidth($str, $size), $y, $str, $size, $rgb, $family);
    }

    public function textWidth(string $str, int $size): float
    {
        $factors = [
            ' ' => 0.28, 'i' => 0.26, 'l' => 0.26, 'I' => 0.30, 'j' => 0.30,
            't' => 0.34, 'f' => 0.30, 'r' => 0.40, 'm' => 0.82, 'w' => 0.78,
            'M' => 0.86, 'W' => 0.92, 'A' => 0.70,
        ];
        $width = 0.0;
        foreach (mb_str_split($str) as $ch) {
            $width += $factors[$ch] ?? (ctype_upper($ch) ? 0.64 : 0.52);
        }
        return $width * $size;
    }

    public function wrapText(string $str, float $maxWidth, int $size): array
    {
        $words = preg_split('/\s+/', trim($str));
        $lines = [];
        $cur = '';
        foreach ($words as $word) {
            $try = $cur === '' ? $word : $cur . ' ' . $word;
            if ($this->textWidth($try, $size) <= $maxWidth || $cur === '') {
                $cur = $try;
            } else {
                $lines[] = $cur;
                $cur = $word;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }
        return $lines;
    }
    /* ================= helpers de reporte ================= */

    public function headerBand(string $subtitle, string $url, string $date): void
    {
        $bandH = 92.0;
        $this->rect(0, 0, $this->pageWidth, $bandH, $this->brand['primary']);
        $this->rect(0, $bandH, $this->pageWidth, 4, $this->brand['accent']);
        $this->text($this->margin, 30, 'JZ DESIGN SOLUTIONS', 14, $this->brand['white']);
        $this->text($this->margin, 56, 'SEO ANALYZER PRO', 22, $this->brand['white']);
        $this->text($this->margin, 80, $subtitle, 9, [215, 235, 236]);
        $this->textRight($this->pageWidth - $this->margin, 34, $url, 10, $this->brand['white']);
        $this->textRight($this->pageWidth - $this->margin, 54, $date, 9, [215, 235, 236]);
        $this->y = $bandH + 16;
    }

    public function sectionTitle(string $title): void
    {
        $this->ensureSpace(46);
        $this->rect($this->margin, $this->y - 2, 4, 16, $this->brand['primary']);
        $this->text($this->margin + 12, $this->y, strtoupper($title), 13, $this->brand['dark']);
        $this->y += 22;
        $this->line($this->margin, $this->y, $this->pageWidth - $this->margin, $this->y, $this->brand['light'], 1);
        $this->y += 12;
    }

    public function progressBar(float $x, float $y, float $w, float $pct, array $color): void
    {
        $this->rect($x, $y, $w, 8, $this->brand['light']);
        $this->rect($x, $y, max(4, $w * min(1, $pct) / 100), 8, $color);
    }

    public function checkRow(string $label, bool $ok, string $detail = ''): void
    {
        $this->ensureSpace(20);
        $color = $ok ? $this->brand['green'] : $this->brand['red'];
        $this->rect($this->margin, $this->y, 10, 10, $color);
        $this->text($this->margin + 18, $this->y + 1, $label, 10, $this->brand['dark']);
        if ($detail !== '') {
            $this->textRight($this->pageWidth - $this->margin, $this->y + 1, $detail, 9, $this->brand['muted']);
        }
        $this->y += 16;
    }

    public function metricRow(string $label, float $val, float $max, string $unit = ''): void
    {
        $this->ensureSpace(26);
        $pct = $max > 0 ? min(100, $val / $max * 100) : 0;
        $color = $pct > 80 ? $this->brand['red'] : ($pct > 50 ? $this->brand['amber'] : $this->brand['green']);
        $this->text($this->margin, $this->y, $label, 10, $this->brand['dark']);
        $formatted = $val == floor($val) ? (string)(int)$val : number_format($val, 1);
        $this->textRight($this->pageWidth - $this->margin, $this->y, $formatted . ' ' . $unit, 9, $this->brand['muted']);
        $this->y += 14;
        $this->progressBar($this->margin, $this->y, $this->pageWidth - $this->margin * 2, $pct, $color);
        $this->y += 12;
    }

    public function grade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A+',
            $score >= 80 => 'A',
            $score >= 70 => 'B',
            $score >= 60 => 'C',
            $score >= 50 => 'D',
            default => 'F',
        };
    }

    public function shortHost(string $url): string
    {
        $host = (string)parse_url($url, PHP_URL_HOST);
        return preg_replace('/^www\./', '', $host) ?: $url;
    }

    public function footer(): void
    {
        $this->ensureSpace(80);
        $h = 60.0;
        $this->rect(0, $this->pageHeight - $h, $this->pageWidth, $h, $this->brand['dark']);
        $this->text($this->margin, $this->pageHeight - 34, 'JZ DESIGN SOLUTIONS', 13, $this->brand['white']);
        $this->text($this->margin, $this->pageHeight - 50, 'https://jzds.me  |  contact@jzds.me  |  +507 6070-0978', 9, [200, 210, 215]);
        $this->textRight($this->pageWidth - $this->margin, $this->pageHeight - 50, 'Generado por SEO Analyzer Pro', 9, [200, 210, 215]);
    }
    /* ================= informe de auditoria ================= */

    public function buildReport(array $report, string $title = 'Informe de Auditoria SEO'): void
    {
        $this->headerBand($title, $report['url'] ?? '', date('d/m/Y H:i'));
        $score = (int)($report['score'] ?? 0);
        $sum = $report['summary'] ?? ['critical' => 0, 'warnings' => 0, 'info' => 0];
        $color = $score >= 80 ? $this->brand['green'] : ($score >= 60 ? $this->brand['amber'] : $this->brand['red']);

        // Tarjeta de score
        $cardW = 170.0;
        $this->ensureSpace(130);
        $this->rect($this->margin, $this->y, $cardW, 120, $this->brand['dark']);
        $sc = (string)$score;
        $this->text($this->margin + ($cardW - $this->textWidth($sc, 46)) / 2, $this->y + 58, $sc, 46, $color);
        $this->text($this->margin + ($cardW - $this->textWidth('SCORE', 11)) / 2, $this->y + 30, 'SCORE', 11, $this->brand['muted']);
        $this->text($this->margin + ($cardW - $this->textWidth($this->grade($score), 13)) / 2, $this->y + 12, $this->grade($score), 13, $color);
        $this->progressBar($this->margin, $this->y - 6, $cardW, $score, $color);

        // Resumen de hallazgos
        $bx = $this->margin + $cardW + 20;
        $bw = $this->pageWidth - $this->margin * 2 - $cardW - 20;
        $this->rect($bx, $this->y, $bw, 120, $this->brand['light']);
        $this->text($bx + 14, $this->y + 92, 'HALLAZGOS', 11, $this->brand['dark']);
        $this->summaryRow($bx + 14, $this->y + 70, 'Criticos', (int)($sum['critical'] ?? 0), $this->brand['red']);
        $this->summaryRow($bx + 14, $this->y + 46, 'Advertencias', (int)($sum['warnings'] ?? 0), $this->brand['amber']);
        $this->summaryRow($bx + 14, $this->y + 22, 'Informacion', (int)($sum['info'] ?? 0), $this->brand['accent']);

        $this->y += 132;

        $this->kpis($report);
        $this->sections($report);
        $this->issuesList($report);
        $this->footer();
    }

    private function summaryRow(float $x, float $y, string $label, int $count, array $color): void
    {
        $this->rect($x, $y, 12, 12, $color);
        $this->text($x + 18, $y + 1, $label, 10, $this->brand['dark']);
        $this->text($x + 120, $y + 1, (string)$count, 10, $this->brand['dark']);
    }

    private function kpis(array $report): void
    {
        $perf = $report['performance'] ?? [];
        $sec = $report['security'] ?? [];
        $meta = $report['meta_tags'] ?? [];
        $items = [
            ['TTFB', isset($perf['ttfb_ms']) ? (string)round($perf['ttfb_ms']) . ' ms' : '-'],
            ['HTTP', strtoupper($perf['http_version'] ?? '-')],
            ['HTTPS', !empty($sec['https']) ? 'SI' : 'NO'],
            ['SSL dias', isset($sec['ssl']['days_left']) ? (string)$sec['ssl']['days_left'] : '-'],
            ['Palabras', (string)($meta['word_count'] ?? 0)],
            ['Imagenes', (string)($report['images']['total'] ?? 0)],
        ];

        $startY = $this->y;
        $this->sectionTitle('Metricas Clave');
        $w = ($this->pageWidth - $this->margin * 2 - 24) / 3;
        $x = $this->margin;
        foreach ($items as $i => [$label, $value]) {
            $col = $x + ($i % 3) * ($w + 12);
            $row = $startY + 46 + intdiv($i, 3) * 46;
            $this->rect($col, $row, $w, 40, $this->brand['light']);
            $this->text($col + 10, $row + 26, $value, 13, $this->brand['dark']);
            $this->text($col + 10, $row + 12, strtoupper($label), 8, $this->brand['muted']);
        }
        $this->y = $startY + 46 + 2 * 46 + 8;
    }

    private function sections(array $report): void
    {
        // Rendimiento
        $perf = $report['performance'] ?? [];
        $this->sectionTitle('Rendimiento');
        $this->metricRow('Tiempo de respuesta (TTFB)', $perf['ttfb_ms'] ?? 0, 500, 'ms');
        $this->metricRow('Recursos de bloqueo', (float)($perf['render_blocking'] ?? 0), 5, '');
        $this->metricRow('Imagenes sin dimensiones', (float)($report['images']['no_dimensions'] ?? 0), 5, '');
        $this->metricRow('Peso total aprox', isset($perf['total_size_kb']) ? (float)round($perf['total_size_kb']) : 0, 2000, 'KB');

        // Seguridad
        $sec = $report['security'] ?? [];
        $this->sectionTitle('Seguridad');
        $this->checkRow('HTTPS activo', !empty($sec['https']), !empty($sec['https']) ? 'OK' : 'FALLA');
        $this->checkRow('HSTS', !empty($sec['hsts']));
        $this->checkRow('X-Frame-Options', !empty($sec['x_frame_options']));
        $this->checkRow('X-Content-Type-Options', !empty($sec['x_content_type_options']));
        $this->checkRow('Content-Security-Policy', !empty($sec['content_security_policy']));
        if (!empty($sec['ssl'])) {
            $this->checkRow('Certificado SSL', true, ($sec['ssl']['days_left'] ?? 0) . ' dias');
        }

        // SEO y contenido
        $meta = $report['meta_tags'] ?? [];
        $this->sectionTitle('SEO y Contenido');
        $titleLen = mb_strlen($meta['title'] ?? '');
        $descLen = mb_strlen($meta['description'] ?? '');
        $this->checkRow('Title optimo (30-65)', $titleLen >= 30 && $titleLen <= 65, $titleLen . ' car.');
        $this->checkRow('Meta description presente', $descLen > 0, $descLen . ' car.');
        $this->checkRow('Canonical', !empty($meta['canonical']));
        $this->checkRow('JSON-LD (Schema.org)', !empty($meta['jsonld']));
        $this->checkRow('Open Graph / Twitter', !empty($meta['og'] ?? []) || !empty($meta['twitter'] ?? []));
        $this->checkRow('sitemap.xml', !empty($meta['sitemap']));
        $this->checkRow('robots.txt', !empty($meta['robots_file']));
    }

    private function issuesList(array $report): void
    {
        $issues = $report['issues'] ?? [];
        if (!$issues) {
            return;
        }
        $this->sectionTitle('Diagnostico Detallado');
        $this->ensureSpace(20);
        $this->text($this->margin, $this->y, 'Total: ' . count($issues) . ' hallazgos', 9, $this->brand['muted']);
        $this->y += 14;

        $order = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($issues, fn($a, $b) => ($order[$a['type'] ?? 'info'] ?? 9) <=> ($order[$b['type'] ?? 'info'] ?? 9));

        $count = 0;
        $maxWidth = $this->pageWidth - $this->margin * 2 - 14;
        foreach ($issues as $issue) {
            if ($count >= 40) {
                $this->text($this->margin, $this->y, '... y ' . (count($issues) - 40) . ' hallazgos mas.', 9, $this->brand['muted']);
                $this->y += 12;
                break;
            }
            $color = match ($issue['type'] ?? 'info') {
                'critical' => $this->brand['red'],
                'warning' => $this->brand['amber'],
                default => $this->brand['accent'],
            };
            $this->ensureSpace(26);
            $this->rect($this->margin, $this->y, 8, 8, $color);
            $cat = strtoupper($issue['category'] ?? 'SEO');
            $msg = (string)($issue['message'] ?? '');
            $this->text($this->margin + 14, $this->y + 1, $cat . ' - ' . $msg, 9, $this->brand['dark']);
            if ($this->textWidth($cat . ' - ' . $msg, 9) > $maxWidth) {
                $lines = $this->wrapText($msg, $maxWidth - $this->textWidth($cat . ' - ', 9), 8);
                foreach (array_slice($lines, 1) as $ln) {
                    $this->ensureSpace(12);
                    $this->text($this->margin + 14, $this->y, $ln, 8, $this->brand['muted']);
                    $this->y += 10;
                }
            }
            $this->y += 16;
            $count++;
        }
    }
    /* ================= comparativa ================= */

    public function buildComparison(array $data): void
    {
        $results = $data['results'] ?? [];
        if (!$results) {
            return;
        }
        $this->headerBand('Comparativa SEO', implode('  |  ', $data['urls'] ?? []), date('d/m/Y H:i'));
        $this->sectionTitle('Puntuacion');

        $n = count($results);
        $totalW = $this->pageWidth - $this->margin * 2;
        $gap = 16.0;
        $w = ($totalW - $gap * ($n - 1)) / $n;
        $x = $this->margin;
        $this->ensureSpace(140);
        foreach ($results as $i => $r) {
            $score = (int)($r['score'] ?? 0);
            $color = $score >= 80 ? $this->brand['green'] : ($score >= 60 ? $this->brand['amber'] : $this->brand['red']);
            $this->rect($x, $this->y, $w, 90, $this->brand['dark']);
            $sc = (string)$score;
            $this->text($x + ($w - $this->textWidth($sc, 30)) / 2, $this->y + 56, $sc, 30, $color);
            $host = $this->shortHost($r['url'] ?? '');
            $this->text($x + ($w - $this->textWidth($host, 9)) / 2, $this->y + 30, $host, 9, $this->brand['white']);
            if (($data['winner_index'] ?? -1) === $i) {
                $this->text($x + ($w - $this->textWidth('GANADOR', 9)) / 2, $this->y + 14, 'GANADOR', 9, $this->brand['accent']);
            }
            $x += $w + $gap;
        }
        $this->y += 104;

        $this->sectionTitle('Metricas Detalladas');
        foreach ($this->comparisonRows($results) as $row) {
            $this->comparisonRow($row, $n);
        }
        $this->footer();
    }

    private function comparisonRows(array $results): array
    {
        $table = [];
        foreach ($results as $r) {
            $sec = $r['security'] ?? [];
            $meta = $r['meta'] ?? [];
            $perf = $r['performance'] ?? [];
            $images = $r['images'] ?? [];
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
                'Img sin alt' => (string)($images['no_alt'] ?? 0),
                'Criticos' => (string)($r['summary']['critical'] ?? 0),
                'Advertencias' => (string)($r['summary']['warnings'] ?? 0),
            ];
        }
        $out = [];
        foreach (array_keys($table[0]) as $name) {
            $out[] = [
                'name' => $name,
                'values' => array_map(fn($row) => $row[$name], $table),
            ];
        }
        return $out;
    }

    private function comparisonRow(array $row, int $n): void
    {
        $this->ensureSpace(24);
        $this->text($this->margin, $this->y, $row['name'], 10, $this->brand['dark']);
        $totalW = $this->pageWidth - $this->margin * 2;
        $gap = 16.0;
        $w = ($totalW - $gap * ($n - 1)) / $n;
        $x = $this->margin;
        foreach ($row['values'] as $v) {
            $this->text($x + ($w - $this->textWidth((string)$v, 10)) / 2, $this->y, (string)$v, 10, $this->brand['primary']);
            $x += $w + $gap;
        }
        $this->y += 16;
        $this->line($this->margin, $this->y, $this->pageWidth - $this->margin, $this->y, $this->brand['light'], 0.6);
        $this->y += 8;
    }

    /* ================= assembly del PDF ================= */

    public function output(?string $file = null): string
    {
        $nPages = $this->pageCount;
        $fontStart = 3 + 2 * $nPages;

        // Recursos de fuentes + objetos de fuentes
        $fontResources = '';
        $fontObjects = [];
        $fi = 0;
        foreach (array_keys($this->fontUsed) as $key) {
            [$family] = explode('|', $key, 2);
            $fontNum = $fontStart + $fi;
            $fontResources .= sprintf('/F%d %d 0 R ', $fi + 1, $fontNum);
            $baseName = match ($family) {
                'courier' => 'Courier',
                'times' => 'Times-Roman',
                default => 'Helvetica',
            };
            $fontObjects[$fontNum] = "<< /Type /Font /Subtype /Type1 /BaseFont /{$baseName} /Encoding /WinAnsiEncoding >>";
            $fi++;
        }

        // Objetos base
        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $kids = [];
        for ($i = 0; $i < $nPages; $i++) {
            $kids[] = (3 + $i) . ' 0 R';
        }
        $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count {$nPages} >>";
        for ($i = 0; $i < $nPages; $i++) {
            $pageNum = 3 + $i;
            $contentNum = 3 + $nPages + $i;
            $objects[$pageNum] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] /Resources << /Font << {$fontResources} >> >> /Contents {$contentNum} 0 R >>";
        }
        for ($i = 0; $i < $nPages; $i++) {
            $contentNum = 3 + $nPages + $i;
            $content = $this->pages[$i];
            $objects[$contentNum] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }
        $last = 2 + 2 * $nPages;
        foreach ($fontObjects as $num => $data) {
            $objects[$num] = $data;
            $last = max($last, $num);
        }
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        for ($num = 1; $num <= $last; $num++) {
            if (!isset($objects[$num])) {
                continue;
            }
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n" . $objects[$num] . "\nendobj\n";
        }
        $xrefStart = strlen($pdf);
        $pdf .= "xref\n0 " . ($last + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($num = 1; $num <= $last; $num++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$num] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($last + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefStart . "\n%%EOF";

        if ($file !== null) {
            file_put_contents($file, $pdf);
        }
        return $pdf;
    }
}