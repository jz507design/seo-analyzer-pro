<?php

error_reporting(0);

$file = $_GET['file'] ?? '';
if ($file === '' || strpbrk($file, "\0\\/") !== false || str_contains($file, '..')) {
    http_response_code(400);
    echo 'Archivo invalido';
    exit;
}

$base = dirname(__DIR__, 2) . '/data/reports/';
$path = $base . basename($file);
if (!is_file($path)) {
    http_response_code(404);
    echo 'No encontrado';
    exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = $ext === 'pdf' ? 'application/pdf' : 'text/html; charset=utf-8';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);