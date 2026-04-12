<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the AI providers below should be the
    | default for AI operations when no explicit provider is provided
    | for the operation. This should be any provider defined below.
    |
    */

    'default' => env('AI_DEFAULT_PROVIDER', 'gemini'),
    'default_for_images' => 'gemini',
    'default_for_audio' => 'eleven',
    'default_for_transcription' => 'openai',
    'default_for_embeddings' => 'gemini',
    'default_for_reranking' => 'cohere',

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Below you may configure caching strategies for AI related operations
    | such as embedding generation. You are free to adjust these values
    | based on your application's available caching stores and needs.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Below are each of your AI providers defined for this application. Each
    | represents an AI provider and API key combination which can be used
    | to perform tasks like text, image, and audio creation via agents.
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-10-21'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
        ],

        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
            'url' => env('GROQ_URL', 'https://api.groq.com/openai/v1'),
        ],

        'jina' => [
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
        ],

        'lmstudio' => [
            'driver' => 'openai',
            'key' => env('LM_STUDIO_API_KEY', 'lm-studio'),
            'url' => env('LM_STUDIO_URL', 'http://127.0.0.1:1234/v1'),
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
            'url' => env('MISTRAL_URL', 'https://api.mistral.ai/v1'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
        ],

        'xai' => [
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
            'url' => env('XAI_URL', 'https://api.x.ai/v1'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Configuration
    |--------------------------------------------------------------------------
    |
    | Configure provider and model per agent type. Each agent checks its own
    | config first, then falls back to the global default_model, then to the
    | provider's built-in default. Swap any agent to a different model or
    | provider by setting the corresponding env variable.
    |
    | Example:
    |   AI_DEFAULT_MODEL=gemma-4-e2b-it-uncensored
    |   AI_PLANNER_PROVIDER=gemini
    |   AI_PLANNER_MODEL=gemini-2.5-flash
    |
    */

    'agents' => [
        'default_model' => env('AI_DEFAULT_MODEL', ''),

        'chat' => [
            'provider' => env('AI_CHAT_PROVIDER'),
            'model' => env('AI_CHAT_MODEL'),
        ],

        'planner' => [
            'provider' => env('AI_PLANNER_PROVIDER'),
            'model' => env('AI_PLANNER_MODEL'),
        ],

        'memory' => [
            'provider' => env('AI_MEMORY_PROVIDER'),
            'model' => env('AI_MEMORY_MODEL'),
        ],

        'event' => [
            'provider' => env('AI_EVENT_PROVIDER'),
            'model' => env('AI_EVENT_MODEL'),
        ],

        'utility' => [
            'provider' => env('AI_UTILITY_PROVIDER'),
            'model' => env('AI_UTILITY_MODEL'),
        ],
    ],

];
