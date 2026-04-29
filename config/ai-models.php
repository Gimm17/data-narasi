<?php

/**
 * Katalog model AI yang tersedia per provider.
 * Digunakan sebagai dropdown suggestions di admin panel.
 * Admin tetap bisa mengetik model ID custom yang tidak ada di daftar.
 *
 * Format: 'provider_slug' => [ ['id' => '...', 'name' => '...', 'desc' => '...'], ... ]
 */
return [

    'nvidia' => [
        ['id' => 'meta/llama-3.1-8b-instruct', 'name' => 'Llama 3.1 8B', 'desc' => 'Fast, efficient for simple tasks'],
        ['id' => 'meta/llama-3.1-70b-instruct', 'name' => 'Llama 3.1 70B', 'desc' => 'High quality, balanced speed'],
        ['id' => 'meta/llama-3.3-70b-instruct', 'name' => 'Llama 3.3 70B', 'desc' => 'Latest Llama, improved reasoning'],
        ['id' => 'meta/llama-4-scout-17b-16e-instruct', 'name' => 'Llama 4 Scout 17B', 'desc' => 'Newest Llama 4 generation'],
        ['id' => 'google/gemma-2-9b-it', 'name' => 'Gemma 2 9B', 'desc' => 'Google open model, lightweight'],
        ['id' => 'google/gemma-3-27b-it', 'name' => 'Gemma 3 27B', 'desc' => 'Google latest, strong reasoning'],
        ['id' => 'mistralai/mistral-7b-instruct-v0.3', 'name' => 'Mistral 7B v0.3', 'desc' => 'Fast European model'],
        ['id' => 'microsoft/phi-4', 'name' => 'Phi-4', 'desc' => 'Microsoft small model, efficient'],
        ['id' => 'deepseek-ai/deepseek-r1-distill-llama-70b', 'name' => 'DeepSeek R1 70B', 'desc' => 'Strong reasoning, Chinese origin'],
        ['id' => 'qwen/qwen2.5-72b-instruct', 'name' => 'Qwen 2.5 72B', 'desc' => 'Alibaba model, multilingual'],
    ],

    'gemini' => [
        ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash', 'desc' => 'Fast, free tier friendly'],
        ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro', 'desc' => 'Higher quality, longer context'],
        ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash', 'desc' => 'Latest generation, fastest'],
        ['id' => 'gemini-2.5-flash-preview-04-17', 'name' => 'Gemini 2.5 Flash Preview', 'desc' => 'Newest preview, thinking model'],
    ],

    'kimi' => [
        ['id' => 'kimi-for-coding', 'name' => 'Kimi for Coding', 'desc' => 'Optimized for code & analysis'],
        ['id' => 'moonshot-v1-8k', 'name' => 'Moonshot v1 8K', 'desc' => 'General purpose, short context'],
        ['id' => 'moonshot-v1-32k', 'name' => 'Moonshot v1 32K', 'desc' => 'General purpose, medium context'],
        ['id' => 'moonshot-v1-128k', 'name' => 'Moonshot v1 128K', 'desc' => 'Long context window'],
    ],

    'glm' => [
        ['id' => 'glm-4-flash', 'name' => 'GLM-4 Flash', 'desc' => 'Free tier, fast response'],
        ['id' => 'glm-4', 'name' => 'GLM-4', 'desc' => 'Full model, better quality'],
        ['id' => 'glm-4-plus', 'name' => 'GLM-4 Plus', 'desc' => 'Premium tier, highest quality'],
    ],

    'minimax' => [
        ['id' => 'MiniMax-M2.5', 'name' => 'MiniMax M2.5', 'desc' => 'Latest model, strong multilingual'],
        ['id' => 'MiniMax-Text-01', 'name' => 'MiniMax Text 01', 'desc' => 'Text generation model'],
    ],

    'claude' => [
        ['id' => 'claude-sonnet-4-20250514', 'name' => 'Claude Sonnet 4', 'desc' => 'Latest balanced model'],
        ['id' => 'claude-3-5-sonnet-20241022', 'name' => 'Claude 3.5 Sonnet', 'desc' => 'Previous gen, reliable'],
        ['id' => 'claude-3-haiku-20240307', 'name' => 'Claude 3 Haiku', 'desc' => 'Cheapest, fastest Claude'],
    ],

];
