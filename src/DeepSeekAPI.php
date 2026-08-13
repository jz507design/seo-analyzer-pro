<?php

declare(strict_types=1);

class DeepSeekAPI
{
    private string $apiKey;
    private string $apiUrl;
    private string $model;
    private int $maxTokens;
    private float $temperature;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->apiUrl = $config['api_url'] ?? 'https://api.deepseek.com/v1/chat/completions';
        $this->model = $config['model'] ?? 'deepseek-chat';
        $this->maxTokens = $config['max_tokens'] ?? 4000;
        $this->temperature = $config['temperature'] ?? 0.7;
    }

    public function analyzeSEO(array $seoData): array
    {
        $prompt = $this->buildPrompt($seoData);

        $response = $this->makeRequest($prompt);

        if ($response === null) {
            throw new Exception('No se pudo obtener respuesta de DeepSeek API');
        }

        return $this->parseResponse($response);
    }

    private function buildPrompt(array $seoData): string
    {
        $json = json_encode($seoData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Eres un experto en SEO con más de 10 años de experiencia. Analiza los siguientes datos de un sitio web y proporciona un informe detallado con sugerencias de mejora.

Datos del análisis SEO:
{$json}

Por favor, proporciona tu respuesta en el siguiente formato JSON:

{
    "resumen": "Resumen ejecutivo del estado SEO del sitio (2-3 párrafos)",
    "puntuacion_general": "Evaluación de la puntuación obtenida y qué significa",
    "fortalezas": [
        "Lista de 3-5 fortalezas encontradas"
    ],
    "problemas_criticos": [
        "Lista de problemas críticos que deben solucionarse inmediatamente"
    ],
    "mejoras_sugeridas": [
        "Lista de 5-10 mejoras sugeridas ordenadas por prioridad"
    ],
    "optimizacion_contenido": {
        "titulo": "Análisis y sugerencias para el título",
        "descripcion": "Análisis y sugerencias para la meta descripción",
        "encabezados": "Análisis de la estructura de encabezados",
        "contenido": "Sugerencias para mejorar el contenido"
    },
    "optimizacion_tecnica": [
        "Sugerencias técnicas de SEO"
    ],
    "optimizacion_imagenes": "Sugerencias para optimizar imágenes",
    "optimizacion_enlaces": "Sugerencias para la estructura de enlaces",
    "acciones_prioritarias": [
        "Top 5 acciones que deben realizarse primero"
    ],
    "consejos_adicionales": "Consejos generales adicionales para mejorar el SEO"
}

Responde ÚNICAMENTE con JSON válido, sin texto adicional antes o después.
PROMPT;
    }

    private function makeRequest(string $prompt): ?array
    {
        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Eres un experto en SEO que responde exclusivamente en formato JSON válido.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'response_format' => ['type' => 'json_object'],
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n" .
                           "Authorization: Bearer {$this->apiKey}\r\n",
                'content' => json_encode($data),
                'timeout' => 60,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($this->apiUrl, false, $context);

        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    private function parseResponse(array $response): array
    {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception('Respuesta inválida de DeepSeek API');
        }

        $content = $response['choices'][0]['message']['content'];
        
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('No se pudo parsear la respuesta JSON de DeepSeek');
        }

        return $decoded;
    }
}
