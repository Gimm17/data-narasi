"""
Cost Calculator
Hitung biaya penggunaan AI berdasarkan model dan jumlah token.
Harga per 1M tokens (USD) — update berkala sesuai pricing provider.
"""

import logging

logger = logging.getLogger(__name__)

# Harga per 1M tokens (input / output) dalam USD
# Source: OpenRouter, TokenRouter pricing pages (May 2026)
# Format: 'model_id': {'input': $/1M, 'output': $/1M}
MODEL_PRICING = {
    # ── OpenAI ──
    'openai/gpt-5.5':              {'input': 2.00, 'output': 8.00},
    'openai/gpt-5.4-pro':          {'input': 5.00, 'output': 15.00},
    'openai/gpt-5.4':              {'input': 2.00, 'output': 8.00},
    'openai/gpt-5.2':              {'input': 1.50, 'output': 6.00},
    'openai/gpt-5-mini':           {'input': 0.30, 'output': 1.20},
    'openai/gpt-5-image':          {'input': 2.50, 'output': 10.00},
    'openai/gpt-5-image-mini':     {'input': 0.40, 'output': 1.60},
    'openai/gpt-4o-mini':          {'input': 0.15, 'output': 0.60},

    # ── Anthropic (Claude) ──
    'anthropic/claude-opus-4.7':   {'input': 15.00, 'output': 75.00},
    'anthropic/claude-sonnet-4.5': {'input': 3.00, 'output': 15.00},
    'claude-haiku-4-5':            {'input': 0.80, 'output': 4.00},

    # ── Google (Gemini) ──
    'google/gemini-3.1-pro-preview':         {'input': 1.25, 'output': 5.00},
    'google/gemini-3-pro-image-preview':     {'input': 1.25, 'output': 5.00},
    'google/gemini-3-flash-preview':         {'input': 0.10, 'output': 0.40},
    'google/gemini-3.1-flash-image-preview': {'input': 0.10, 'output': 0.40},
    'google/gemini-2.5-flash-image':         {'input': 0.10, 'output': 0.40},
    'google/gemini-2.5-flash':               {'input': 0.10, 'output': 0.40},

    # ── DeepSeek ──
    'deepseek/deepseek-v4-pro':    {'input': 0.50, 'output': 2.00},
    'deepseek/deepseek-v4-flash':  {'input': 0.10, 'output': 0.40},
    'deepseek/deepseek-v3.2':      {'input': 0.27, 'output': 1.10},
    'deepseek/deepseek-chat-v3-0324': {'input': 0.27, 'output': 1.10},

    # ── Qwen ──
    'qwen/qwen3.6-plus':           {'input': 0.50, 'output': 2.00},
    'qwen/qwen3.5-397b-a17b':      {'input': 0.80, 'output': 3.20},
    'qwen/qwen3.5-122b-a10b':      {'input': 0.30, 'output': 1.20},
    'qwen/qwen3.5-plus-02-15':     {'input': 0.50, 'output': 2.00},
    'qwen/qwen3.5-flash':          {'input': 0.05, 'output': 0.20},
    'qwen/qwen3.5-35b-a3b':        {'input': 0.15, 'output': 0.60},
    'qwen/qwen3.5-9b':             {'input': 0.05, 'output': 0.20},
    'qwen/qwen3-coder-next':       {'input': 0.30, 'output': 1.20},
    'qwen/qwen3-235b-a22b':        {'input': 0.80, 'output': 3.20},

    # ── xAI (Grok) ──
    'x-ai/grok-4.3':              {'input': 3.00, 'output': 15.00},
    'x-ai/grok-4.20-beta':        {'input': 3.00, 'output': 15.00},
    'x-ai/grok-4.1-fast':         {'input': 5.00, 'output': 15.00},

    # ── MiniMax ──
    'minimax/minimax-m2.7':          {'input': 0.50, 'output': 2.00},
    'minimax/minimax-m2.7-highspeed': {'input': 0.20, 'output': 0.80},
    'minimax/minimax-m2.5':          {'input': 0.30, 'output': 1.20},
    'minimax/minimax-m2.1':          {'input': 0.20, 'output': 0.80},

    # ── Xiaomi (MiMo) ──
    'xiaomi/mimo-v2.5-pro':        {'input': 0.30, 'output': 1.20},
    'xiaomi/mimo-v2.5':            {'input': 0.15, 'output': 0.60},
    'xiaomi/mimo-v2-pro':          {'input': 0.20, 'output': 0.80},
    'xiaomi/mimo-v2-omni':         {'input': 0.15, 'output': 0.60},
    'xiaomi/mimo-v2-flash':        {'input': 0.05, 'output': 0.20},

    # ── NVIDIA ──
    'nvidia/nemotron-3-super-120b-a12b':                {'input': 0.30, 'output': 1.20},
    'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free': {'input': 0.00, 'output': 0.00},
    'meta/llama-3.1-8b-instruct':  {'input': 0.05, 'output': 0.08},

    # ── Mistral ──
    'mistralai/devstral-2512':     {'input': 0.20, 'output': 0.60},

    # ── Z.AI (GLM) ──
    'z-ai/glm-5.1':               {'input': 0.50, 'output': 2.00},
    'z-ai/glm-5':                  {'input': 0.40, 'output': 1.60},
    'z-ai/glm-5-turbo':            {'input': 0.15, 'output': 0.60},
    'z-ai/glm-4.7':                {'input': 0.30, 'output': 1.20},
    'z-ai/glm-4.6':                {'input': 0.20, 'output': 0.80},

    # ── Others ──
    'moonshotai/kimi-k2.6':        {'input': 0.30, 'output': 1.20},
    'moonshotai/kimi-k2.5':        {'input': 0.20, 'output': 0.80},
    'stepfun/step-3.5-flash':      {'input': 0.10, 'output': 0.40},
    'meta-llama/llama-4-maverick': {'input': 0.00, 'output': 0.00},

    # ── Legacy / Other providers ──
    'gemini-2.5-flash':            {'input': 0.10, 'output': 0.40},
    'kimi-for-coding':             {'input': 0.20, 'output': 0.80},
    'glm-4-flash':                 {'input': 0.05, 'output': 0.20},
}

# Fallback harga jika model tidak ditemukan
DEFAULT_PRICING = {'input': 0.50, 'output': 2.00}


def calculate_cost(model_id: str, prompt_tokens: int, completion_tokens: int) -> float:
    """
    Hitung biaya dalam USD berdasarkan model dan token count.

    Args:
        model_id: ID model (e.g. 'openai/gpt-5.5')
        prompt_tokens: Jumlah token input
        completion_tokens: Jumlah token output

    Returns:
        Cost dalam USD (float, 6 decimal places)
    """
    pricing = MODEL_PRICING.get(model_id, DEFAULT_PRICING)

    input_cost = (prompt_tokens / 1_000_000) * pricing['input']
    output_cost = (completion_tokens / 1_000_000) * pricing['output']
    total_cost = input_cost + output_cost

    logger.debug(
        f"Cost for {model_id}: {prompt_tokens} in × ${pricing['input']}/1M "
        f"+ {completion_tokens} out × ${pricing['output']}/1M = ${total_cost:.6f}"
    )

    return round(total_cost, 6)


def get_pricing(model_id: str) -> dict:
    """Get pricing info for a model"""
    return MODEL_PRICING.get(model_id, DEFAULT_PRICING)
