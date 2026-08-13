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

$useAI = $input['use_ai'] ?? true;
$apiKey = $input['api_key'] ?? '';

$configFile = dirname(__DIR__, 2) . '/config/config.php';
$seoFile = dirname(__DIR__, 2) . '/src/SEOAnalyzer.php';

if (!file_exists($configFile)) {
    echo json_encode(['error' => 'Config file not found']);
    exit;
}

if (!file_exists($seoFile)) {
    echo json_encode(['error' => 'SEOAnalyzer file not found: ' . $seoFile]);
    exit;
}

$config = require $configFile;
require_once $seoFile;

try {
    $analyzer = new SEOAnalyzer($url);
    $seoReport = $analyzer->analyze();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

$aiAnalysis = null;

if ($useAI && !empty($apiKey) && $apiKey !== 'TU_API_KEY_AQUI') {
    $deepseekFile = dirname(__DIR__, 2) . '/src/DeepSeekAPI.php';
    if (file_exists($deepseekFile)) {
        require_once $deepseekFile;
        $deepseekConfig = $config['deepseek'] ?? [];
        $deepseekConfig['api_key'] = $apiKey;
        
        try {
            $api = new DeepSeekAPI($deepseekConfig);
            $aiAnalysis = $api->analyzeSEO($seoReport);
        } catch (Exception $e) {
            $aiAnalysis = ['error' => $e->getMessage()];
        }
    }
} elseif ($useAI) {
    $aiAnalysis = ['error' => 'API Key no configurada'];
}

echo json_encode([
    'success' => true,
    'seo_report' => $seoReport,
    'ai_analysis' => $aiAnalysis,
    'timestamp' => date('Y-m-d H:i:s'),
]);