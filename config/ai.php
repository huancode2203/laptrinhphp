<?php

return [
    'search' => ['provider' => env('AI_SEARCH_PROVIDER', 'local'), 'model' => env('AI_SEARCH_MODEL', 'qwen3:4b')],
    'moderation' => ['provider' => env('AI_MODERATION_PROVIDER', 'local'), 'model' => env('AI_MODERATION_MODEL', 'qwen3:4b'), 'auto_approve' => env('AI_AUTO_APPROVE', true)],
    'openai_key' => env('OPENAI_API_KEY'), 'ollama_url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'), 'timeout' => (int) env('AI_TIMEOUT', 20),
];
