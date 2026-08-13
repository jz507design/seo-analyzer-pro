<?php

declare(strict_types=1);

/**
 * Configuracion de SEO Analyzer Pro.
 * La API key NUNCA se guarda en este archivo: se lee de la variable de
 * entorno DEEPSEEK_API_KEY (o se pasa por flag/prompt en CLI, o por POST
 * en la web). Asi el repositorio es seguro para compartir/clonar.
 */

return [
    'deepseek' => [
        'api_key' => getenv('DEEPSEEK_API_KEY') ?: '',
        'api_url' => 'https://api.deepseek.com/v1/chat/completions',
        'model' => 'deepseek-chat',
        'max_tokens' => 4000,
        'temperature' => 0.7,
    ],
    'seo' => [
        'max_content_length' => 100000,
        'timeout' => 45,
        'user_agent' => 'SEO-Analyzer/1.0',
    ],
    'app' => [
        'name' => 'SEO Analyzer Pro',
        'version' => '2.0.0',
    ],
];