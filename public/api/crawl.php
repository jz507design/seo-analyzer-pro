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
$maxPages = max(1, min(30, (int)($input['max_pages'] ?? 10)));

$base = dirname(__DIR__, 2);
require_once $base . '/src/Crawler.php';

try {
    $crawler = new Crawler($url, $maxPages);
    $result = $crawler->crawl();
    echo json_encode(['success' => true] + $result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}