<?php

declare(strict_types=1);

/**
 * Router del servidor web local (php -S ... bin/router.php).
 * Sirve archivos estaticos de public/ y la API bajo /api/.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$docroot = dirname(__DIR__) . '/public';

// Seguridad: normalizar y evitar path traversal
$file = realpath($docroot . $uri);
if ($file === false || !str_starts_with($file, realpath($docroot))) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

// Si es un archivo existente, servirlo (pero los .php se ejecutan, no se sirven crudos)
if (is_file($file)) {
    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
        require $file;
        exit;
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = [
        'html' => 'text/html; charset=utf-8',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'ico' => 'image/x-icon',
        'txt' => 'text/plain',
    ];
    header('Content-Type: ' . ($mime[$ext] ?? 'application/octet-stream'));
    readfile($file);
    exit;
}

// API
if (str_starts_with($uri, '/api/')) {
    $apiFile = $docroot . $uri . (str_ends_with($uri, '.php') ? '' : '.php');
    if (is_file($apiFile)) {
        require $apiFile;
        exit;
    }
}

// Todo lo demas -> index.html (SPA simple)
readfile($docroot . '/index.html');