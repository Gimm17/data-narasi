<?php

/**
 * Katalog model AI yang tersedia per provider.
 * Digunakan sebagai dropdown suggestions di admin panel.
 * Admin tetap bisa mengetik model ID custom yang tidak ada di daftar.
 *
 * Format: 'provider_slug' => [ ['id' => '...', 'name' => '...', 'desc' => '...'], ... ]
 *
 * NVIDIA models scraped from: https://build.nvidia.com/models (April 2026)
 */
return [

    'nvidia' => [
        // ── Meta Llama ──
        ['id' => 'meta/llama-4-maverick-17b-128e-instruct', 'name' => 'Llama 4 Maverick 17B', 'desc' => 'Newest Llama 4, MoE 128 experts'],
        ['id' => 'meta/llama-3.3-70b-instruct', 'name' => 'Llama 3.3 70B', 'desc' => 'Latest Llama 3, strong reasoning'],
        ['id' => 'meta/llama-3.2-3b-instruct', 'name' => 'Llama 3.2 3B', 'desc' => 'Lightweight, edge device ready'],
        ['id' => 'meta/llama-3.2-11b-vision-instruct', 'name' => 'Llama 3.2 11B Vision', 'desc' => 'Multimodal vision+text'],
        ['id' => 'meta/llama-3.1-405b-instruct', 'name' => 'Llama 3.1 405B', 'desc' => 'Largest open model, highest quality'],
        ['id' => 'meta/llama-3.1-70b-instruct', 'name' => 'Llama 3.1 70B', 'desc' => 'High quality, balanced speed'],
        ['id' => 'meta/llama-3.1-8b-instruct', 'name' => 'Llama 3.1 8B', 'desc' => 'Fast, efficient for simple tasks'],
        ['id' => 'meta/llama-3-70b-instruct', 'name' => 'Llama 3 70B', 'desc' => 'Previous gen, still reliable'],
        ['id' => 'meta/llama-3-8b-instruct', 'name' => 'Llama 3 8B', 'desc' => 'Previous gen, lightweight'],

        // ── Google Gemma ──
        ['id' => 'google/gemma-4-31b-it', 'name' => 'Gemma 4 31B', 'desc' => 'Latest Gemma, strongest quality'],
        ['id' => 'google/gemma-3n-e4b-it', 'name' => 'Gemma 3n E4B', 'desc' => 'Nano variant, 4B effective'],
        ['id' => 'google/gemma-3n-e2b-it', 'name' => 'Gemma 3n E2B', 'desc' => 'Nano variant, ultra-light'],
        ['id' => 'google/gemma-3-27b-it', 'name' => 'Gemma 3 27B', 'desc' => 'Google mid-size, strong reasoning'],
        ['id' => 'google/gemma-2-27b-it', 'name' => 'Gemma 2 27B', 'desc' => 'Previous gen, proven reliable'],
        ['id' => 'google/gemma-2-9b-it', 'name' => 'Gemma 2 9B', 'desc' => 'Lightweight Google model'],
        ['id' => 'google/gemma-2-2b-it', 'name' => 'Gemma 2 2B', 'desc' => 'Ultra-light, fastest inference'],

        // ── Mistral ──
        ['id' => 'mistralai/mistral-large-3-675b-instruct-2512', 'name' => 'Mistral Large 3 675B', 'desc' => 'Largest Mistral, premium quality'],
        ['id' => 'mistralai/mistral-large-4-119b-2603', 'name' => 'Mistral Large 4 119B', 'desc' => 'Latest large, balanced'],
        ['id' => 'mistralai/mistral-small-4-119b-2603', 'name' => 'Mistral Small 4 119B', 'desc' => 'Efficient large model'],
        ['id' => 'mistralai/mistral-nemo-12b-instruct', 'name' => 'Mistral Nemo 12B', 'desc' => 'NVIDIA co-dev, efficient'],
        ['id' => 'mistralai/mistral-7b-instruct-v0.3', 'name' => 'Mistral 7B v0.3', 'desc' => 'Classic lightweight model'],
        ['id' => 'mistralai/mixtral-8x22b-instruct-v0.1', 'name' => 'Mixtral 8x22B', 'desc' => 'MoE, high quality'],
        ['id' => 'mistralai/mixtral-8x7b-instruct-v0.1', 'name' => 'Mixtral 8x7B', 'desc' => 'MoE, balanced speed/quality'],
        ['id' => 'mistralai/codestral-22b-v0.1', 'name' => 'Codestral 22B', 'desc' => 'Code-specialized model'],
        ['id' => 'mistralai/ministral-14b-instruct-2512', 'name' => 'Ministral 14B', 'desc' => 'Small efficient model'],
        ['id' => 'mistralai/mistral-nemotron', 'name' => 'Mistral Nemotron', 'desc' => 'NVIDIA-optimized Mistral'],
        ['id' => 'mistralai/pixtral-12b', 'name' => 'Pixtral 12B', 'desc' => 'Vision-language model'],

        // ── DeepSeek ──
        ['id' => 'deepseek-ai/deepseek-v4-flash', 'name' => 'DeepSeek V4 Flash', 'desc' => 'Latest, fast inference'],
        ['id' => 'deepseek-ai/deepseek-v4-pro', 'name' => 'DeepSeek V4 Pro', 'desc' => 'Latest, highest quality'],
        ['id' => 'deepseek-ai/deepseek-v3.2', 'name' => 'DeepSeek V3.2', 'desc' => 'Strong reasoning model'],
        ['id' => 'deepseek-ai/deepseek-v3.1-terminus', 'name' => 'DeepSeek V3.1 Terminus', 'desc' => 'Endpoint-optimized'],
        ['id' => 'deepseek-ai/deepseek-coder-v2-instruct', 'name' => 'DeepSeek Coder V2', 'desc' => 'Code-specialized'],

        // ── Qwen ──
        ['id' => 'qwen/qwen3.5-397b-a17b', 'name' => 'Qwen 3.5 397B MoE', 'desc' => 'Largest Qwen, premium'],
        ['id' => 'qwen/qwen3.5-122b-a10b', 'name' => 'Qwen 3.5 122B MoE', 'desc' => 'Mid-size MoE, balanced'],
        ['id' => 'qwen/qwen3-next-80b-a3b-instruct', 'name' => 'Qwen 3 Next 80B Instruct', 'desc' => 'Instruction-tuned'],
        ['id' => 'qwen/qwen3-next-80b-a3b-thinking', 'name' => 'Qwen 3 Next 80B Thinking', 'desc' => 'Chain-of-thought reasoning'],
        ['id' => 'qwen/qwen3-coder-480b-a35b-instruct', 'name' => 'Qwen 3 Coder 480B', 'desc' => 'Massive code model'],
        ['id' => 'qwen/qwen2.5-coder-32b-instruct', 'name' => 'Qwen 2.5 Coder 32B', 'desc' => 'Proven code model'],

        // ── Microsoft Phi ──
        ['id' => 'microsoft/phi-4-mini-instruct', 'name' => 'Phi 4 Mini', 'desc' => 'Latest Phi, small & efficient'],
        ['id' => 'microsoft/phi-4-multimodal-instruct', 'name' => 'Phi 4 Multimodal', 'desc' => 'Vision+text small model'],
        ['id' => 'microsoft/phi-3-medium-128k-instruct', 'name' => 'Phi 3 Medium 128K', 'desc' => 'Long context, 128K tokens'],
        ['id' => 'microsoft/phi-3-mini-128k-instruct', 'name' => 'Phi 3 Mini 128K', 'desc' => 'Lightweight, long context'],

        // ── NVIDIA Nemotron ──
        ['id' => 'nvidia/llama-3.3-nemotron-super-49b-v1.5', 'name' => 'Nemotron Super 49B', 'desc' => 'NVIDIA-tuned Llama 3.3'],
        ['id' => 'nvidia/nemotron-4-340b-instruct', 'name' => 'Nemotron 4 340B', 'desc' => 'Massive NVIDIA model'],
        ['id' => 'nvidia/nemotron-3-super-120b-a12b', 'name' => 'Nemotron 3 Super 120B', 'desc' => 'MoE, 12B active params'],
        ['id' => 'nvidia/nemotron-3-nano-30b-a3b', 'name' => 'Nemotron 3 Nano 30B', 'desc' => 'Compact, 3B active params'],
        ['id' => 'nvidia/nvidia-nemotron-nano-9b-v2', 'name' => 'Nemotron Nano 9B v2', 'desc' => 'Small, fast, efficient'],
        ['id' => 'nvidia/nemotron-mini-4b-instruct', 'name' => 'Nemotron Mini 4B', 'desc' => 'Tiniest NVIDIA model'],

        // ── Other ──
        ['id' => 'snowflake/arctic', 'name' => 'Snowflake Arctic', 'desc' => 'Enterprise-focused MoE'],
        ['id' => 'minimaxai/minimax-m2.7', 'name' => 'MiniMax M2.7', 'desc' => 'MiniMax via NVIDIA endpoint'],
    ],

    'gemini' => [
        ['id' => 'gemini-2.5-flash-preview-04-17', 'name' => 'Gemini 2.5 Flash Preview', 'desc' => 'Newest thinking model'],
        ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash', 'desc' => 'Latest generation, fastest'],
        ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro', 'desc' => 'Higher quality, longer context'],
        ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash', 'desc' => 'Fast, free tier friendly'],
    ],

    'kimi' => [
        ['id' => 'kimi-for-coding', 'name' => 'Kimi for Coding', 'desc' => 'Optimized for code & analysis'],
        ['id' => 'moonshot-v1-128k', 'name' => 'Moonshot v1 128K', 'desc' => 'Long context window'],
        ['id' => 'moonshot-v1-32k', 'name' => 'Moonshot v1 32K', 'desc' => 'General purpose, medium context'],
        ['id' => 'moonshot-v1-8k', 'name' => 'Moonshot v1 8K', 'desc' => 'General purpose, short context'],
    ],

    'glm' => [
        ['id' => 'glm-4-plus', 'name' => 'GLM-4 Plus', 'desc' => 'Premium tier, highest quality'],
        ['id' => 'glm-4', 'name' => 'GLM-4', 'desc' => 'Full model, better quality'],
        ['id' => 'glm-4-flash', 'name' => 'GLM-4 Flash', 'desc' => 'Free tier, fast response'],
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
