<?php

/**
 * Katalog model AI yang tersedia per provider.
 * Digunakan sebagai dropdown suggestions di admin panel.
 * Admin tetap bisa mengetik model ID custom yang tidak ada di daftar.
 *
 * Format: 'provider_slug' => [ ['id' => '...', 'name' => '...', 'desc' => '...'], ... ]
 *
 * Sources:
 * - NVIDIA: https://build.nvidia.com/models (April 2026)
 * - Gemini: https://ai.google.dev/gemini-api/docs/models (April 2026)
 * - Claude: https://docs.anthropic.com/en/docs/about-claude/models (April 2026)
 * - Kimi:   https://platform.moonshot.cn (April 2026)
 * - GLM:    https://open.bigmodel.cn (April 2026)
 * - MiniMax: https://platform.minimaxi.com (April 2026)
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

    // ────────────────────────────────────────────────
    // GEMINI — Source: https://ai.google.dev/gemini-api/docs/models
    // ────────────────────────────────────────────────
    'gemini' => [
        // ── Gemini 3 Series (Preview) ──
        ['id' => 'gemini-3.1-pro-preview', 'name' => 'Gemini 3.1 Pro', 'desc' => 'Advanced intelligence, agentic & vibe coding (Preview)'],
        ['id' => 'gemini-3-flash-preview', 'name' => 'Gemini 3 Flash', 'desc' => 'Frontier-class at fraction of cost (Preview)'],
        ['id' => 'gemini-3.1-flash-lite-preview', 'name' => 'Gemini 3.1 Flash-Lite', 'desc' => 'Fastest Gemini 3, ultra-cheap (Preview)'],

        // ── Gemini 2.5 Series (Stable) ──
        ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash', 'desc' => 'Best price-performance, reasoning (Stable)'],
        ['id' => 'gemini-2.5-flash-lite', 'name' => 'Gemini 2.5 Flash-Lite', 'desc' => 'Fastest, most budget-friendly 2.5'],
        ['id' => 'gemini-2.5-pro', 'name' => 'Gemini 2.5 Pro', 'desc' => 'Most advanced, deep reasoning & coding'],
        ['id' => 'gemini-2.5-flash-preview-04-17', 'name' => 'Gemini 2.5 Flash Preview', 'desc' => 'Preview thinking model'],

        // ── Gemini 2.0 Series (Deprecated but available) ──
        ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash', 'desc' => '2nd gen workhorse, 1M context (Deprecated)'],
        ['id' => 'gemini-2.0-flash-lite', 'name' => 'Gemini 2.0 Flash-Lite', 'desc' => 'Fastest 2nd gen (Deprecated)'],

        // ── Gemini 1.5 Series (Legacy) ──
        ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro', 'desc' => 'Long context, 2M tokens (Legacy)'],
        ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash', 'desc' => 'Fast, free tier friendly (Legacy)'],

        // ── Latest Aliases ──
        ['id' => 'gemini-flash-latest', 'name' => 'Gemini Flash (Latest)', 'desc' => 'Auto-swaps to newest Flash model'],
    ],

    // ────────────────────────────────────────────────
    // KIMI / MOONSHOT — Source: platform.moonshot.cn
    // ────────────────────────────────────────────────
    'kimi' => [
        // ── K2 Series (Current) ──
        ['id' => 'kimi-k2.6', 'name' => 'Kimi K2.6', 'desc' => 'Most intelligent, native multimodal+agent'],
        ['id' => 'kimi-k2.5', 'name' => 'Kimi K2.5', 'desc' => 'MoE architecture, agent swarm'],

        // ── Specialized ──
        ['id' => 'kimi-for-coding', 'name' => 'Kimi for Coding', 'desc' => 'Optimized for code & analysis'],

        // ── Moonshot V1 (Legacy) ──
        ['id' => 'moonshot-v1-128k', 'name' => 'Moonshot v1 128K', 'desc' => 'Long context window (Legacy)'],
        ['id' => 'moonshot-v1-32k', 'name' => 'Moonshot v1 32K', 'desc' => 'General purpose, medium context'],
        ['id' => 'moonshot-v1-8k', 'name' => 'Moonshot v1 8K', 'desc' => 'General purpose, short context'],
    ],

    // ────────────────────────────────────────────────
    // GLM (ZhiPu AI) — Source: open.bigmodel.cn
    // ────────────────────────────────────────────────
    'glm' => [
        // ── GLM-5 Series (Latest) ──
        ['id' => 'glm-5.1', 'name' => 'GLM-5.1', 'desc' => 'Latest flagship, agentic AI'],
        ['id' => 'glm-4.7', 'name' => 'GLM-4.7', 'desc' => 'Tool calling specialist'],

        // ── GLM-4 Series ──
        ['id' => 'glm-4-plus', 'name' => 'GLM-4 Plus', 'desc' => 'Premium tier, highest quality'],
        ['id' => 'glm-4', 'name' => 'GLM-4', 'desc' => 'Full model, good quality'],
        ['id' => 'glm-4-flash', 'name' => 'GLM-4 Flash', 'desc' => 'Free tier, fast response'],
        ['id' => 'glm-4-flashx', 'name' => 'GLM-4 FlashX', 'desc' => 'Ultra-fast inference variant'],
        ['id' => 'glm-4-airx', 'name' => 'GLM-4 AirX', 'desc' => 'Balanced speed/quality'],
        ['id' => 'glm-4-air', 'name' => 'GLM-4 Air', 'desc' => 'Lightweight, efficient'],
        ['id' => 'glm-4-long', 'name' => 'GLM-4 Long', 'desc' => 'Extended context, 1M tokens'],
        ['id' => 'glm-4-alltools', 'name' => 'GLM-4 AllTools', 'desc' => 'Code interpreter + web search'],

        // ── GLM-4V (Vision) ──
        ['id' => 'glm-4v', 'name' => 'GLM-4V', 'desc' => 'Vision understanding model'],
        ['id' => 'glm-4v-plus', 'name' => 'GLM-4V Plus', 'desc' => 'Enhanced vision model'],
        ['id' => 'glm-4v-flash', 'name' => 'GLM-4V Flash', 'desc' => 'Fast vision model (Free)'],

        // ── Legacy ──
        ['id' => 'glm-3-turbo', 'name' => 'GLM-3 Turbo', 'desc' => 'Previous gen, budget-friendly'],
    ],

    // ────────────────────────────────────────────────
    // MINIMAX — Source: platform.minimaxi.com
    // ────────────────────────────────────────────────
    'minimax' => [
        // ── M2 Series (Current) ──
        ['id' => 'MiniMax-M2.7', 'name' => 'MiniMax M2.7', 'desc' => 'Latest flagship, strongest quality'],
        ['id' => 'MiniMax-M2.7-highspeed', 'name' => 'MiniMax M2.7 HighSpeed', 'desc' => 'Fast variant of M2.7'],
        ['id' => 'MiniMax-M2.5', 'name' => 'MiniMax M2.5', 'desc' => 'Strong multilingual model'],
        ['id' => 'MiniMax-M2.5-highspeed', 'name' => 'MiniMax M2.5 HighSpeed', 'desc' => 'Fast variant of M2.5'],
        ['id' => 'MiniMax-M2.1', 'name' => 'MiniMax M2.1', 'desc' => 'Previous gen, reliable'],
        ['id' => 'MiniMax-M2.1-highspeed', 'name' => 'MiniMax M2.1 HighSpeed', 'desc' => 'Fast variant of M2.1'],
        ['id' => 'MiniMax-M2', 'name' => 'MiniMax M2', 'desc' => 'Base M2 model'],

        // ── Specialized ──
        ['id' => 'M2-her', 'name' => 'M2 Her', 'desc' => 'Roleplay/character specialized'],
        ['id' => 'MiniMax-Text-01', 'name' => 'MiniMax Text 01', 'desc' => 'Legacy text generation'],
    ],

    // ────────────────────────────────────────────────
    // CLAUDE (Anthropic) — Source: docs.anthropic.com
    // ────────────────────────────────────────────────
    'claude' => [
        // ── Claude 4 Series (Latest) ──
        ['id' => 'claude-opus-4-20250514', 'name' => 'Claude Opus 4.7', 'desc' => 'Most capable, agentic coding champion'],
        ['id' => 'claude-sonnet-4-20250514', 'name' => 'Claude Sonnet 4', 'desc' => 'Latest balanced, great for coding'],

        // ── Claude 3.5 Series ──
        ['id' => 'claude-3-5-sonnet-20241022', 'name' => 'Claude 3.5 Sonnet', 'desc' => 'Previous gen, proven reliable'],
        ['id' => 'claude-3-5-haiku-20241022', 'name' => 'Claude 3.5 Haiku', 'desc' => 'Fast, efficient, cheapest 3.5'],

        // ── Claude 3 Series ──
        ['id' => 'claude-3-opus-20240229', 'name' => 'Claude 3 Opus', 'desc' => 'Previous flagship, complex tasks'],
        ['id' => 'claude-3-sonnet-20240229', 'name' => 'Claude 3 Sonnet', 'desc' => 'Previous mid-tier'],
        ['id' => 'claude-3-haiku-20240307', 'name' => 'Claude 3 Haiku', 'desc' => 'Cheapest, fastest Claude'],
    ],

];
