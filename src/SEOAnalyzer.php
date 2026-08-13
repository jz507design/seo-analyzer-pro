<?php

declare(strict_types=1);

require_once __DIR__ . '/HttpFetch.php';

/**
 * SEOAnalyzer - Motor de diagnostico tecnico para sitios web.
 * Genera reporte 0-100 con categorias: meta, estructura, rendimiento,
 * seguridad, SEO avanzado, accesibilidad y tecnologias detectadas.
 */
class SEOAnalyzer
{
    private string $url;
    private string $html = '';
    private array $metaTags = [];
    private array $headings = [];
    private array $images = [];
    private array $links = [];
    private array $issues = [];
    private int $score = 100;

    private array $httpHeaders = [];
    private array $headerMap = [];
    private bool $fetched = false;
    private float $ttfbMs = 0.0;
    private string $httpVersion = '';
    private string $compression = '';
    private array $cookies = [];
    private array $sslInfo = [];
    private array $performance = [];
    private array $security = [];
    private array $technologies = [];
    private array $accessibility = [];

    public function __construct(string $url)
    {
        $this->url = filter_var($url, FILTER_VALIDATE_URL) ? $url : 'https://' . $url;
    }

    public function analyze(): array
    {
        if (!$this->fetched) {
            $this->fetchPage();
        }
        $this->parseMetaTags();
        $this->parseHeadings();
        $this->parseImages();
        $this->parseLinks();
        $this->parsePerformance();
        $this->parseSecurity();
        $this->parseTechnologies();
        $this->parseAccessibility();
        $this->checkSEO();
        $this->calculateScore();

        return $this->getReport();
    }    /* ============================================================
     * FETCH
     * ============================================================ */


    /**
     * Inyecta HTML ya descargado (usado por Crawler para evitar doble fetch).
     */
    public function setRawHTML(string $html, array $headerMap = [], float $ttfbMs = 0.0, string $compression = ''): void
    {
        $this->html = $html;
        $this->ttfbMs = $ttfbMs;
        $this->compression = $compression;
        $this->headerMap = $headerMap;
        $this->fetched = true;
    }

    private function fetchPage(): void
    {
        $config = require __DIR__ . '/../config/config.php';
        $timeout = $config['seo']['timeout'] ?? 30;
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
        $html = @file_get_contents($this->url, false, $context);
        $this->ttfbMs = round((microtime(true) - $start) * 1000, 1);

        if ($html === false) {
            throw new Exception("No se pudo acceder a la URL: {$this->url}");
        }

        // Captura headers y descomprime si el servidor envio gzip (PHP no lo hace solo por defecto)
        if (isset($http_response_header) && is_array($http_response_header)) {
            $this->httpHeaders = $http_response_header;
            foreach ($http_response_header as $header) {
                if (stripos($header, 'Content-Encoding:') === 0) {
                    $enc = strtoupper(trim(substr($header, strlen('Content-Encoding:'))));
                    $this->compression = $enc;
                    if (strpos($enc, 'GZIP') !== false) {
                        $decoded = @gzdecode($html);
                        if ($decoded !== false) {
                            $html = $decoded;
                        }
                    } elseif (strpos($enc, 'BR') !== false && function_exists('brotli_uncompress')) {
                        $decoded = @brotli_uncompress($html);
                        if ($decoded !== false) {
                            $html = $decoded;
                        }
                    }
                }
                if (preg_match('#^HTTP/\d(\.\d)?\s#i', $header)) {
                    $this->httpVersion = trim(strtok($header, ' '));
                }
                if (stripos($header, 'Set-Cookie:') === 0) {
                    $this->cookies[] = trim(substr($header, strlen('Set-Cookie:')));
                }
            }
        }

        $maxLength = $config['seo']['max_content_length'] ?? 100000;
        $this->html = substr($html, 0, $maxLength);
    }

    /* ============================================================
     * DOM helpers
     * ============================================================ */

    private function createDOM(): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        libxml_use_internal_errors(true);
        @$dom->loadHTML($this->html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        return $dom;
    }

    private function xpath(): DOMXPath
    {
        return new DOMXPath($this->createDOM());
    }    /* ============================================================
     * META
     * ============================================================ */

    private function parseMetaTags(): void
    {
        $xpath = $this->xpath();

        $titleNodes = $xpath->query('//title');
        $this->metaTags['title'] = $titleNodes->length > 0 ? trim($titleNodes->item(0)->textContent) : '';

        $desc = $xpath->query('//meta[@name="description"]/@content');
        $this->metaTags['description'] = $desc->length > 0 ? trim($desc->item(0)->textContent) : '';

        $keywords = $xpath->query('//meta[@name="keywords"]/@content');
        $this->metaTags['keywords'] = $keywords->length > 0 ? trim($keywords->item(0)->textContent) : '';

        $robots = $xpath->query('//meta[@name="robots"]/@content');
        $this->metaTags['robots'] = $robots->length > 0 ? trim($robots->item(0)->textContent) : '';

        $ogTags = [];
        foreach ($xpath->query('//meta[starts-with(@property, "og:")]') as $node) {
            $ogTags[$node->getAttribute('property')] = $node->getAttribute('content');
        }
        $this->metaTags['og'] = $ogTags;

        $twitterTags = [];
        foreach ($xpath->query('//meta[starts-with(@name, "twitter:")]') as $node) {
            $twitterTags[$node->getAttribute('name')] = $node->getAttribute('content');
        }
        $this->metaTags['twitter'] = $twitterTags;

        $canonical = $xpath->query('//link[@rel="canonical"]/@href');
        $this->metaTags['canonical'] = $canonical->length > 0 ? trim($canonical->item(0)->textContent) : '';

        $viewport = $xpath->query('//meta[@name="viewport"]/@content');
        $this->metaTags['viewport'] = $viewport->length > 0 ? trim($viewport->item(0)->textContent) : '';

        $charset = $xpath->query('//meta[@charset]/@charset');
        $this->metaTags['charset'] = $charset->length > 0 ? trim($charset->item(0)->textContent) : '';

        $lang = $xpath->query('//html/@lang');
        $this->metaTags['lang'] = $lang->length > 0 ? trim($lang->item(0)->textContent) : '';

        $themeColor = $xpath->query('//meta[@name="theme-color"]/@content');
        $this->metaTags['theme_color'] = $themeColor->length > 0 ? trim($themeColor->item(0)->textContent) : '';

        $favicon = $xpath->query('//link[@rel="icon"]/@href');
        $this->metaTags['favicon'] = $favicon->length > 0 ? trim($favicon->item(0)->textContent) : '';

        $appleIcon = $xpath->query('//link[@rel="apple-touch-icon"]/@href');
        $this->metaTags['apple_touch_icon'] = $appleIcon->length > 0 ? trim($appleIcon->item(0)->textContent) : '';

        $hreflang = [];
        foreach ($xpath->query('//link[@rel="alternate"][@hreflang]') as $node) {
            $hreflang[$node->getAttribute('hreflang')] = $node->getAttribute('href');
        }
        $this->metaTags['hreflang'] = $hreflang;

        // Datos estructurados JSON-LD (Schema.org)
        $jsonLd = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $node) {
            $content = trim($node->textContent);
            if ($content !== '') {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $type = $decoded['@type'] ?? ($decoded[0]['@type'] ?? 'Unknown');
                    $jsonLd[] = [
                        'type' => is_array($type) ? implode(',', $type) : (string)$type,
                        'raw' => substr($content, 0, 200),
                    ];
                }
            }
        }
        $this->metaTags['jsonld'] = $jsonLd;

        $generator = $xpath->query('//meta[@name="generator"]/@content');
        $this->metaTags['generator'] = $generator->length > 0 ? trim($generator->item(0)->textContent) : '';
    }    /* ============================================================
     * HEADINGS / IMAGES / LINKS
     * ============================================================ */

    private function parseHeadings(): void
    {
        $xpath = $this->xpath();
        for ($i = 1; $i <= 6; $i++) {
            $headings = [];
            foreach ($xpath->query("//h{$i}") as $node) {
                $headings[] = trim($node->textContent);
            }
            $this->headings["h{$i}"] = $headings;
        }
    }

    private function parseImages(): void
    {
        $xpath = $this->xpath();
        foreach ($xpath->query('//img') as $node) {
            $alt = $node->getAttribute('alt');
            $src = $node->getAttribute('src');
            if ($src === '') {
                $src = $node->getAttribute('data-src') ?: $node->getAttribute('data-lazy-src');
            }
            $this->images[] = [
                'src' => $src,
                'alt' => $alt,
                'has_alt' => $node->hasAttribute('alt') && $alt !== '',
                'alt_empty' => $node->hasAttribute('alt') && $alt === '',
                'lazy' => $node->getAttribute('loading') === 'lazy' || $node->hasAttribute('data-src') || $node->hasAttribute('data-lazy-src'),
                'has_dimensions' => $node->getAttribute('width') !== '' && $node->getAttribute('height') !== '',
                'modern_format' => (bool)preg_match('/\.(webp|avif)(\?|#|$)/i', $src),
            ];
        }
    }

    private function parseLinks(): void
    {
        $xpath = $this->xpath();
        $parsedUrl = parse_url($this->url);
        $host = strtolower($parsedUrl['host'] ?? '');
        $scheme = strtolower($parsedUrl['scheme'] ?? 'https');

        $internal = 0;
        $external = 0;
        $noFollow = 0;
        $emptyHref = 0;
        $mixedContent = 0;

        foreach ($xpath->query('//a[@href]') as $node) {
            $href = trim($node->getAttribute('href'));
            if ($href === '' || $href === '#') {
                $emptyHref++;
                continue;
            }

            $linkParsed = parse_url($href);
            $linkHost = strtolower($linkParsed['host'] ?? '');

            if ($linkHost === '' || $linkHost === $host) {
                $internal++;
            } else {
                $external++;
                // Mixed content: enlace http en pagina https
                if ($scheme === 'https' && ($linkParsed['scheme'] ?? '') === 'http') {
                    $mixedContent++;
                }
            }

            $rel = $node->getAttribute('rel');
            if (stripos($rel, 'nofollow') !== false) {
                $noFollow++;
            }
        }

        $this->links = [
            'internal' => $internal,
            'external' => $external,
            'nofollow' => $noFollow,
            'empty' => $emptyHref,
            'total' => $internal + $external + $emptyHref,
            'mixed_content' => $mixedContent,
        ];
    }    /* ============================================================
     * PERFORMANCE
     * ============================================================ */

    private function parsePerformance(): void
    {
        $xpath = $this->xpath();

        // Scripts: conteo, externos, async/defer
        $scriptCount = 0;
        $renderBlocking = 0;
        foreach ($xpath->query('//script') as $node) {
            $scriptCount++;
            if ($node->getAttribute('src') !== '' && !$node->hasAttribute('defer') && !$node->hasAttribute('async')) {
                $renderBlocking++;
            }
        }

        // CSS en head sin media query = bloquea render
        $cssBlocking = 0;
        foreach ($xpath->query('/html/head/link[@rel="stylesheet"]') as $node) {
            if ($node->getAttribute('media') === '') {
                $cssBlocking++;
            }
        }

        // Inline CSS/JS size
        $inlineJsBytes = 0;
        foreach ($xpath->query('//script[not(@src)]') as $node) {
            $inlineJsBytes += strlen($node->textContent);
        }
        $inlineCssBytes = 0;
        foreach ($xpath->query('//style') as $node) {
            $inlineCssBytes += strlen($node->textContent);
        }

        $htmlSize = strlen($this->html);
        $wordCount = $this->countWords();

        $this->metaTags['word_count'] = $wordCount;
        $this->metaTags['html_size'] = $htmlSize;

        $this->performance = [
            'ttfb_ms' => $this->ttfbMs,
            'http_version' => $this->httpVersion,
            'compression' => $this->compression !== '' ? $this->compression : 'Ninguna',
            'compression_ok' => $this->compression !== '',
            'html_size_kb' => round($htmlSize / 1024, 1),
            'inline_js_kb' => round($inlineJsBytes / 1024, 1),
            'inline_css_kb' => round($inlineCssBytes / 1024, 1),
            'script_count' => $scriptCount,
            'render_blocking_scripts' => $renderBlocking,
            'render_blocking_css' => $cssBlocking,
            'render_blocking_total' => $renderBlocking + $cssBlocking,
            'stylesheet_count' => count($xpath->query('//link[@rel="stylesheet"]')),
            'total_requests' => $scriptCount + count($xpath->query('//link[@rel="stylesheet"]')) + count($this->images),
            'lazy_images' => count(array_filter($this->images, fn($i) => $i['lazy'])),
            'images_without_dimensions' => count(array_filter($this->images, fn($i) => !$i['has_dimensions'])),
            'modern_images' => count(array_filter($this->images, fn($i) => $i['modern_format'])),
        ];
    }

    private function countWords(): int
    {
        $dom = $this->createDOM();
        $body = $dom->getElementsByTagName('body')->item(0);
        $text = $body ? trim($body->textContent) : '';
        return str_word_count($text);
    }    /* ============================================================
     * SECURITY
     * ============================================================ */

    private function parseSecurity(): void
    {
        $headerMap = $this->headerMap;
        if (empty($headerMap)) {
            foreach ($this->httpHeaders as $header) {
                if (strpos($header, ':') !== false) {
                    [$name, $value] = explode(':', $header, 2);
                    $headerMap[strtolower(trim($name))] = trim($value);
                }
            }
        }

        $isHttps = str_starts_with($this->url, 'https://');

        $this->security = [
            'https' => $isHttps,
            'hsts' => $headerMap['strict-transport-security'] ?? '',
            'x_frame_options' => $headerMap['x-frame-options'] ?? '',
            'x_content_type_options' => $headerMap['x-content-type-options'] ?? '',
            'content_security_policy' => $headerMap['content-security-policy'] ?? '',
            'referrer_policy' => $headerMap['referrer-policy'] ?? '',
            'permissions_policy' => $headerMap['permissions-policy'] ?? '',
            'server_header' => $headerMap['server'] ?? '',
            'x_powered_by' => $headerMap['x-powered-by'] ?? '',
            'cookies' => [
                'total' => count($this->cookies),
                'secure' => count(array_filter($this->cookies, fn($c) => stripos($c, 'secure') !== false)),
                'httponly' => count(array_filter($this->cookies, fn($c) => stripos($c, 'httponly') !== false)),
                'samesite' => count(array_filter($this->cookies, fn($c) => stripos($c, 'samesite') !== false)),
            ],
            'mixed_content' => $this->links['mixed_content'] ?? 0,
            'ssl' => $isHttps ? $this->getSSLInfo() : null,
        ];
    }

    private function getSSLInfo(): array
    {
        $parsed = parse_url($this->url);
        $host = $parsed['host'] ?? '';
        $port = $parsed['port'] ?? 443;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'capture_peer_cert' => true,
            ],
        ]);

        $client = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            8,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$client) {
            return ['error' => $errstr ?: 'Conexion SSL fallida'];
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) {
            return ['error' => 'Sin certificado capturado'];
        }

        $data = openssl_x509_parse($cert);
        if (!$data) {
            return ['error' => 'Certificado no parseable'];
        }

        $validTo = $data['validTo_time_t'] ?? 0;
        $daysLeft = (int)ceil(($validTo - time()) / 86400);

        return [
            'subject' => $data['subject']['CN'] ?? '',
            'issuer' => $data['issuer']['CN'] ?? '',
            'valid_from' => date('Y-m-d', $data['validFrom_time_t'] ?? time()),
            'valid_to' => date('Y-m-d', $validTo),
            'days_left' => $daysLeft,
        ];
    }    /* ============================================================
     * TECHNOLOGIES / ACCESSIBILITY
     * ============================================================ */

    private function parseTechnologies(): void
    {
        $html = strtolower($this->html);
        $found = [];

        $detections = [
            'WordPress' => ['/wp-content\//i', '/wp-includes\//i'],
            'Wix' => ['/wix\.com/i'],
            'Shopify' => ['/cdn\.shopify\.com/i'],
            'Squarespace' => ['/squarespace/i'],
            'Webflow' => ['/webflow/i'],
            'Joomla' => ['/joomla/i'],
            'Drupal' => ['/drupal/i'],
            'PrestaShop' => ['/prestashop/i'],
            'Magento' => ['/magento/i'],
            'React' => ['/react/i', '/_next\/static/i'],
            'Next.js' => ['/_next\//i'],
            'Vue' => ['/vue\.js/i', '/__vue__/i'],
            'Angular' => ['/ng-version/i'],
            'Svelte' => ['/svelte/i'],
            'Bootstrap' => ['/bootstrap/i'],
            'Tailwind CSS' => ['/tailwind/i'],
            'jQuery' => ['/jquery/i'],
            'GSAP' => ['/gsap/i'],
            'Chart.js' => ['/chart\.js/i', '/chartjs/i'],
            'Google Analytics' => ['/google-analytics/i', '/googletagmanager/i', '/gtag\(/i'],
            'Meta Pixel' => ['/connect\.facebook\.net/i', '/fbq\(/i'],
            'TikTok Pixel' => ['/analytics\.tiktok\.com/i'],
            'Cloudflare' => ['/cloudflare/i'],
            'Laravel' => ['/laravel/i', '/csrf-token/i'],
            'Django' => ['/django/i', '/csrftoken/i'],
            'Font Awesome' => ['/font-awesome/i', '/fontawesome/i'],
            'Material Icons' => ['/material-icons/i'],
            'Alpine.js' => ['/alpinejs/i', '/alpine\.js/i'],
            'HTMX' => ['/htmx/i'],
            'Lodash' => ['/lodash/i'],
        ];

        foreach ($detections as $tech => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html)) {
                    $found[] = $tech;
                    break;
                }
            }
        }

        $this->technologies = array_values(array_unique($found));
    }

    private function parseAccessibility(): void
    {
        $xpath = $this->xpath();

        $linksWithoutText = 0;
        foreach ($xpath->query('//a[@href]') as $node) {
            $text = trim($node->textContent);
            $imgAlt = '';
            foreach ($node->getElementsByTagName('img') as $img) {
                $imgAlt .= $img->getAttribute('alt');
            }
            if ($text === '' && $imgAlt === '' && $node->getAttribute('aria-label') === '') {
                $linksWithoutText++;
            }
        }

        $inputsWithoutLabel = 0;
        foreach ($xpath->query('//form//input[not(@type="hidden")]') as $node) {
            $id = $node->getAttribute('id');
            $hasLabel = $id !== '' && $xpath->query("//label[@for='{$id}']")->length > 0;
            if (!$hasLabel && $node->getAttribute('aria-label') === '') {
                $inputsWithoutLabel++;
            }
        }

        $iframesWithoutTitle = 0;
        foreach ($xpath->query('//iframe') as $node) {
            if ($node->getAttribute('title') === '' && $node->getAttribute('aria-label') === '') {
                $iframesWithoutTitle++;
            }
        }

        $this->accessibility = [
            'links_without_text' => $linksWithoutText,
            'inputs_without_label' => $inputsWithoutLabel,
            'iframes_without_title' => $iframesWithoutTitle,
            'lang_set' => $this->metaTags['lang'] !== '',
        ];
    }    /* ============================================================
     * CHECKS
     * ============================================================ */

    private function checkSEO(): void
    {
        $this->checkTitle();
        $this->checkDescription();
        $this->checkHeadings();
        $this->checkImages();
        $this->checkLinks();
        $this->checkTechnical();
        $this->checkContent();
        $this->checkPerformance();
        $this->checkSecurity();
        $this->checkSEOAdvanced();
        $this->checkAccessibility();
    }

    private function addIssue(string $type, string $category, string $message, string $suggestion, int $penalty): void
    {
        $this->issues[] = [
            'type' => $type,
            'category' => $category,
            'message' => $message,
            'suggestion' => $suggestion,
        ];
        $this->score -= $penalty;
    }

    private function checkTitle(): void
    {
        $title = $this->metaTags['title'] ?? '';
        $len = mb_strlen($title);

        if ($title === '') {
            $this->addIssue('critical', 'Meta Tags', 'Falta la etiqueta <title>. Es fundamental para SEO.', 'Agrega un titulo descriptivo entre 50-60 caracteres.', 15);
        } elseif ($len < 30) {
            $this->addIssue('warning', 'Meta Tags', "El titulo es muy corto ({$len} caracteres).", 'El titulo ideal debe tener entre 50-60 caracteres.', 5);
        } elseif ($len > 60) {
            $this->addIssue('warning', 'Meta Tags', "El titulo es muy largo ({$len} caracteres).", 'Google trunca titulos mayores a 60 caracteres.', 5);
        }
    }

    private function checkDescription(): void
    {
        $description = $this->metaTags['description'] ?? '';
        $len = mb_strlen($description);

        if ($description === '') {
            $this->addIssue('critical', 'Meta Tags', 'Falta la meta descripcion.', 'Agrega una meta descripcion entre 150-160 caracteres.', 10);
        } elseif ($len < 70) {
            $this->addIssue('warning', 'Meta Tags', "La meta descripcion es muy corta ({$len} caracteres).", 'La descripcion ideal debe tener entre 150-160 caracteres.', 5);
        } elseif ($len > 160) {
            $this->addIssue('warning', 'Meta Tags', "La meta descripcion es muy larga ({$len} caracteres).", 'Google trunca descripciones mayores a 160 caracteres.', 5);
        }
    }

    private function checkHeadings(): void
    {
        $h1Count = count($this->headings['h1'] ?? []);

        if ($h1Count === 0) {
            $this->addIssue('critical', 'Estructura', 'No se encontro ninguna etiqueta <h1>.', 'Agrega exactamente un <h1> por pagina con tu palabra clave principal.', 10);
        } elseif ($h1Count > 1) {
            $this->addIssue('warning', 'Estructura', "Se encontraron {$h1Count} etiquetas <h1>.", 'Solo debe haber un <h1> por pagina.', 5);
        }

        $hasH2 = count($this->headings['h2'] ?? []) > 0;
        if (!$hasH2 && $h1Count > 0) {
            $this->addIssue('info', 'Estructura', 'No se encontraron etiquetas <h2>.', 'Usa <h2> para dividir el contenido en secciones.', 3);
        }
    }    private function checkImages(): void
    {
        $total = count($this->images);
        $withoutAlt = count(array_filter($this->images, fn($i) => !$i['has_alt'] || $i['alt_empty']));

        if ($total > 0 && $withoutAlt > 0) {
            $this->addIssue('warning', 'Imagenes', "{$withoutAlt} de {$total} imagenes no tienen atributo alt.", 'Agrega descripciones alt a todas las imagenes para mejorar accesibilidad y SEO.', min(10, $withoutAlt * 2));
        }

        if ($total > 20) {
            $this->addIssue('info', 'Imagenes', "Se encontraron {$total} imagenes.", 'Muchas imagenes pueden ralentizar la carga. Considera optimizarlas.', 0);
        }
    }

    private function checkLinks(): void
    {
        if (($this->links['empty'] ?? 0) > 0) {
            $this->addIssue('warning', 'Enlaces', "{$this->links['empty']} enlaces vacios o con href='#'.", 'Los enlaces vacios no son utiles para SEO ni accesibilidad.', 3);
        }

        if (($this->links['total'] ?? 0) === 0) {
            $this->addIssue('warning', 'Enlaces', 'No se encontraron enlaces en la pagina.', 'Agrega enlaces internos y externos relevantes.', 5);
        }

        if (($this->links['mixed_content'] ?? 0) > 0) {
            $this->addIssue('warning', 'Seguridad', "{$this->links['mixed_content']} enlaces externos usan HTTP en un sitio HTTPS.", 'Cambia esos enlaces a HTTPS (mixed content penaliza).', 4);
        }
    }

    private function checkTechnical(): void
    {
        if (empty($this->metaTags['lang'] ?? '')) {
            $this->addIssue('warning', 'Tecnico', 'No se encontro el atributo lang en <html>.', 'Define el idioma del documento con lang="es" para espanol.', 5);
        }

        if (empty($this->metaTags['viewport'] ?? '')) {
            $this->addIssue('critical', 'Tecnico', 'Falta la meta etiqueta viewport.', 'Agrega <meta name="viewport" content="width=device-width, initial-scale=1.0">.', 10);
        }

        if (empty($this->metaTags['charset'] ?? '')) {
            $this->addIssue('warning', 'Tecnico', 'No se encontro la declaracion de charset.', 'Agrega <meta charset="UTF-8"> al inicio del <head>.', 3);
        }

        if (empty($this->metaTags['canonical'] ?? '')) {
            $this->addIssue('info', 'Tecnico', 'No se encontro URL canonica.', 'Agrega una URL canonica para evitar contenido duplicado.', 3);
        }

        if (!str_starts_with($this->url, 'https://')) {
            $this->addIssue('critical', 'Seguridad', 'El sitio no usa HTTPS.', 'HTTPS es un factor de ranking y un requisito de confianza. Migra a HTTPS.', 10);
        }
    }

    private function checkContent(): void
    {
        $wordCount = $this->metaTags['word_count'] ?? 0;
        $htmlSize = $this->metaTags['html_size'] ?? 0;

        if ($wordCount < 300) {
            $this->addIssue('warning', 'Contenido', "La pagina tiene aproximadamente {$wordCount} palabras.", 'El contenido ideal debe tener al menos 300 palabras para SEO.', 5);
        }

        if ($htmlSize > 150000) {
            $this->addIssue('warning', 'Contenido', "El HTML es muy grande (" . round($htmlSize / 1024, 2) . " KB).", 'Reduce el tamano del HTML para mejorar el tiempo de carga.', 5);
        }
    }    private function checkPerformance(): void
    {
        $perf = $this->performance;

        if (($perf['ttfb_ms'] ?? 0) > 1500) {
            $this->addIssue('warning', 'Rendimiento', "Tiempo de respuesta alto: {$perf['ttfb_ms']} ms.", 'Optimiza el servidor: cache, CDN o mejor hosting. Objetivo: bajo 500 ms.', 8);
        } elseif (($perf['ttfb_ms'] ?? 0) > 600) {
            $this->addIssue('info', 'Rendimiento', "Tiempo de respuesta: {$perf['ttfb_ms']} ms.", 'Un TTFB saludable esta bajo 600 ms. Considera cache y CDN.', 3);
        }

        if (!$perf['compression_ok']) {
            $this->addIssue('warning', 'Rendimiento', 'El servidor no comprime las respuestas (gzip/brotli).', 'Activa la compresion en tu hosting para reducir el peso transferido.', 5);
        }

        if (($perf['render_blocking_total'] ?? 0) > 0) {
            $this->addIssue('warning', 'Rendimiento', "{$perf['render_blocking_total']} recursos bloquean el renderizado inicial.", 'Usa defer/async en scripts y carga CSS critico primero.', 6);
        }

        if (($perf['script_count'] ?? 0) > 20) {
            $this->addIssue('info', 'Rendimiento', "La pagina carga {$perf['script_count']} scripts.", 'Demasiados scripts aumentan peticiones. Consolida y minifica.', 3);
        }

        if (($perf['total_requests'] ?? 0) > 80) {
            $this->addIssue('info', 'Rendimiento', "La pagina hace {$perf['total_requests']} peticiones estimadas.", 'Cuantas mas peticiones, mas lenta la carga. Combina y optimiza.', 3);
        }

        if (($perf['modern_images'] ?? 0) === 0 && (count($this->images) ?? 0) > 0) {
            $this->addIssue('info', 'Rendimiento', 'No se detectaron imagenes en formato moderno (WebP/AVIF).', 'Usa WebP/AVIF para reducir el peso de imagenes hasta 30-50%.', 3);
        }
    }

    private function checkSecurity(): void
    {
        $sec = $this->security;

        if (empty($sec['hsts'])) {
            $this->addIssue('warning', 'Seguridad', 'Falta el header HSTS (HTTP Strict Transport Security).', 'Agrega Strict-Transport-Security para forzar HTTPS.', 4);
        }

        if (empty($sec['x_frame_options'])) {
            $this->addIssue('info', 'Seguridad', 'Falta X-Frame-Options (proteccion contra clickjacking).', 'Agrega X-Frame-Options: SAMEORIGIN.', 2);
        }

        if (empty($sec['x_content_type_options'])) {
            $this->addIssue('info', 'Seguridad', 'Falta X-Content-Type-Options.', 'Agrega X-Content-Type-Options: nosniff.', 2);
        }

        if (empty($sec['content_security_policy'])) {
            $this->addIssue('info', 'Seguridad', 'No se detecto Content-Security-Policy.', 'Una CSP bien configurada protege contra XSS.', 2);
        }

        $cookies = $sec['cookies'] ?? [];
        if (($cookies['total'] ?? 0) > 0) {
            $notSecure = $cookies['total'] - ($cookies['secure'] ?? 0);
            if ($notSecure > 0) {
                $this->addIssue('warning', 'Seguridad', "{$notSecure} de {$cookies['total']} cookies no tienen flag Secure.", 'Marca las cookies con Secure para que solo viajen por HTTPS.', 4);
            }
            $notHttpOnly = $cookies['total'] - ($cookies['httponly'] ?? 0);
            if ($notHttpOnly > 0) {
                $this->addIssue('info', 'Seguridad', "{$notHttpOnly} de {$cookies['total']} cookies no tienen flag HttpOnly.", 'HttpOnly protege las cookies de acceso via JavaScript (XSS).', 2);
            }
        }

        if (!empty($sec['x_powered_by'])) {
            $this->addIssue('info', 'Seguridad', "El servidor expone X-Powered-By: {$sec['x_powered_by']}.", 'Ocultar la firma del servidor reduce la superficie de ataque.', 1);
        }

        $ssl = $sec['ssl'] ?? null;
        if (is_array($ssl) && isset($ssl['days_left']) && $ssl['days_left'] < 30) {
            $this->addIssue('warning', 'Seguridad', "El certificado SSL expira en {$ssl['days_left']} dias.", 'Renueva el certificado antes de que expire para evitar fallos.', 8);
        }
    }    private function checkSEOAdvanced(): void
    {
        $og = $this->metaTags['og'] ?? [];
        $requiredOg = ['og:title', 'og:description', 'og:image', 'og:type', 'og:url'];
        $missingOg = [];
        foreach ($requiredOg as $r) {
            if (empty($og[$r] ?? '')) {
                $missingOg[] = $r;
            }
        }
        if (count($missingOg) > 0) {
            $this->addIssue('warning', 'SEO Avanzado', 'Open Graph incompleto: faltan ' . implode(', ', $missingOg) . '.', 'Completa las etiquetas OG para compartir correctamente en redes.', 5);
        }

        if (empty($this->metaTags['twitter'] ?? [])) {
            $this->addIssue('info', 'SEO Avanzado', 'No hay Twitter Cards.', 'Agrega twitter:card y twitter:title para mejorar la vista en X.', 2);
        }

        if (empty($this->metaTags['jsonld'] ?? [])) {
            $this->addIssue('info', 'SEO Avanzado', 'No hay datos estructurados JSON-LD (Schema.org).', 'Agrega JSON-LD (LocalBusiness, Product, Article) para rich snippets.', 4);
        } else {
            $types = implode(', ', array_column($this->metaTags['jsonld'], 'type'));
            $this->addIssue('info', 'SEO Avanzado', "JSON-LD detectado: {$types}.", 'Bien. Verifica que los datos sean validos en el validador de Google.', 0);
        }

        if (empty($this->metaTags['favicon'] ?? '')) {
            $this->addIssue('info', 'SEO Avanzado', 'No se encontro favicon.', 'Agrega un favicon para reforzar la marca en el navegador.', 1);
        }

        if (empty($this->metaTags['hreflang'] ?? [])) {
            $this->addIssue('info', 'SEO Avanzado', 'No hay etiquetas hreflang.', 'Solo es necesario si el sitio tiene versiones multi-idioma/multi-pais.', 0);
        }

        $this->checkSitemapRobots();
    }

    private function checkSitemapRobots(): void
    {
        $parsed = parse_url($this->url);
        $origin = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        $sitemap = HttpFetch::status($origin . '/sitemap.xml', 12);
        $this->metaTags['sitemap'] = $sitemap === 200 || $sitemap === 0 ? ($sitemap === 200) : null;
        if ($sitemap === 200) {
            $this->addIssue('info', 'SEO Avanzado', 'sitemap.xml detectado.', 'Correcto. Mantenlo actualizado.', 0);
        } elseif ($sitemap !== 0) {
            $this->addIssue('info', 'SEO Avanzado', 'sitemap.xml no encontrado (HTTP ' . $sitemap . ').', 'Agrega un sitemap.xml para facilitar el indexado.', 3);
        }

        $robots = HttpFetch::status($origin . '/robots.txt', 12);
        $this->metaTags['robots_file'] = $robots === 200 || $robots === 0 ? ($robots === 200) : null;
        if ($robots === 200) {
            $this->addIssue('info', 'SEO Avanzado', 'robots.txt detectado.', 'Correcto. Verifica que no bloquee paginas importantes.', 0);
        } elseif ($robots !== 0) {
            $this->addIssue('info', 'SEO Avanzado', 'robots.txt no encontrado (HTTP ' . $robots . ').', 'Agrega un robots.txt basico.', 3);
        }
    }

    private function checkAccessibility(): void
    {
        $acc = $this->accessibility;

        if (($acc['links_without_text'] ?? 0) > 0) {
            $this->addIssue('warning', 'Accesibilidad', "{$acc['links_without_text']} enlaces sin texto ni aria-label.", 'Los enlaces deben ser comprensibles fuera de contexto (screen readers).', 4);
        }

        if (($acc['inputs_without_label'] ?? 0) > 0) {
            $this->addIssue('warning', 'Accesibilidad', "{$acc['inputs_without_label']} inputs de formulario sin label.", 'Cada input necesita un <label> o aria-label asociado.', 4);
        }

        if (($acc['iframes_without_title'] ?? 0) > 0) {
            $this->addIssue('info', 'Accesibilidad', "{$acc['iframes_without_title']} iframes sin atributo title.", 'Agrega title descriptivo a los iframes para accesibilidad.', 2);
        }
    }    /* ============================================================
     * SCORE & REPORT
     * ============================================================ */

    private function calculateScore(): void
    {
        $this->score = max(0, min(100, $this->score));
    }

    private function getReport(): array
    {
        return [
            'url' => $this->url,
            'score' => $this->score,
            'meta_tags' => $this->metaTags,
            'headings' => $this->headings,
            'images' => [
                'total' => count($this->images),
                'without_alt' => count(array_filter($this->images, fn($i) => !$i['has_alt'] || $i['alt_empty'])),
                'lazy' => count(array_filter($this->images, fn($i) => $i['lazy'])),
                'without_dimensions' => count(array_filter($this->images, fn($i) => !$i['has_dimensions'])),
                'modern' => count(array_filter($this->images, fn($i) => $i['modern_format'])),
            ],
            'links' => $this->links,
            'performance' => $this->performance ?? [],
            'security' => $this->security ?? [],
            'technologies' => $this->technologies ?? [],
            'accessibility' => $this->accessibility ?? [],
            'issues' => $this->issues,
            'summary' => [
                'critical' => count(array_filter($this->issues, fn($i) => $i['type'] === 'critical')),
                'warnings' => count(array_filter($this->issues, fn($i) => $i['type'] === 'warning')),
                'info' => count(array_filter($this->issues, fn($i) => $i['type'] === 'info')),
            ],
        ];
    }

    public function getRawHTML(): string
    {
        return $this->html;
    }
}