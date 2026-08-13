<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (empty($input['url'])) {
    http_response_code(400);
    echo json_encode(['error' => 'URL es requerida']);
    exit;
}

$url = $input['url'];
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    $url = 'https://' . $url;
}

$format = $input['format'] ?? 'pdf';
if (!in_array($format, ['pdf', 'html'], true)) {
    $format = 'pdf';
}

$base = dirname(__DIR__, 2);
require_once $base . '/src/SEOAnalyzer.php';
require_once $base . '/src/ReportPDF.php';
require_once $base . '/src/ReportHTML.php';

try {
    $analyzer = new SEOAnalyzer($url);
    $report = $analyzer->analyze();

    $outDir = $base . '/data/reports';
    if (!is_dir($outDir)) {
        mkdir($outDir, 0775, true);
    }

    $slug = preg_replace('/[^a-z0-9]/i', '-', (string)parse_url($url, PHP_URL_HOST));
    $slug = trim($slug, '-') ?: 'sitio';
    $file = $outDir . '/' . $slug . '-' . date('Ymd-His') . '.' . $format;

    if ($format === 'pdf') {
        $pdf = new ReportPDF('portrait');
        $pdf->buildReport($report);
        $pdf->output($file);
    } else {
        $html = new ReportHTML();
        file_put_contents($file, $html->buildReport($report));
    }

    echo json_encode([
        'success' => true,
        'format' => $format,
        'url' => $url,
        'score' => $report['score'] ?? 0,
        'file' => basename($file),
        'download' => '/api/download.php?file=' . urlencode(basename($file)),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}