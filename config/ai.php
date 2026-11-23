<?php

return [
    'provider' => env('AI_PROVIDER', 'openrouter'),

    // Embeddings may use a different provider than chat: stored chunk vectors
    // are only comparable to query vectors from the SAME embedding model, so
    // switching chat to gemini/anthropic must not switch embeddings.
    'embed_provider' => env('AI_EMBED_PROVIDER'),

    // Free tiers go down (Gemini 503 "high demand", OpenRouter empty bodies).
    // When the chat provider fails, LlmService retries once on this one.
    // Chat only — embeddings must never switch model. Empty = no fallback.
    'fallback' => env('AI_FALLBACK_PROVIDER', 'openrouter'),

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-pro'),
    ],

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'chat_model' => env('OPENROUTER_CHAT_MODEL', 'nvidia/nemotron-3-ultra-550b-a55b:free'),
        'embed_model' => env('OPENROUTER_EMBED_MODEL', 'openai/text-embedding-3-small'),
    ],

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'chat_model' => env('OLLAMA_CHAT_MODEL', 'qwen2.5:7b'),
        'embed_model' => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5'),
    ],

    'freellmapi' => [
        'key' => env('FREELLMAPI_API_KEY'),
        'base_url' => env('FREELLMAPI_BASE_URL', 'http://127.0.0.1:3001/v1'),
        'chat_model' => env('FREELLMAPI_CHAT_MODEL', 'auto'),
    ],
];
