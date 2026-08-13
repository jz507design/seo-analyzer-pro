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

$urls = array_values(array_filter(array_map('trim', $input['urls'] ?? [])));
if (count($urls) < 2 || count($urls) > 3) {
    http_response_code(400);
    echo json_encode(['error' => 'Se requieren 2 o 3 URLs']);
    exit;
}

$base = dirname(__DIR__, 2);
require_once $base . '/src/Comparador.php';
require_once $base . '/src/ReportPDF.php';
require_once $base . '/src/ReportHTML.php';

try {
    $comparador = new Comparador($urls);
    $data = $comparador->compare();

    $format = $input['format'] ?? 'json';
    if ($format === 'pdf' || $format === 'html') {
        $outDir = $base . '/data/reports';
        if (!is_dir($outDir)) {
            mkdir($outDir, 0775, true);
        }
        $file = $outDir . '/comparativa-' . date('Ymd-His') . '.' . $format;
        if ($format === 'pdf') {
            $pdf = new ReportPDF('portrait');
            $pdf->buildComparison($data);
            $pdf->output($file);
        } else {
            $html = new ReportHTML();
            file_put_contents($file, $html->buildComparison($data));
        }
        echo json_encode([
            'success' => true,
            'format' => $format,
            'file' => basename($file),
            'download' => '/download.php?file=' . urlencode(basename($file)),
            'winner_index' => $data['winner_index'],
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'results' => $data['results'],
            'winner_index' => $data['winner_index'],
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}