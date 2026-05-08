"""
OpenRouter Provider
Unified API gateway untuk 300+ AI models via openrouter.ai
OpenAI-compatible: https://openrouter.ai/api/v1/chat/completions
"""

import re
import logging
from openai import OpenAI
from .base import BaseAIProvider

logger = logging.getLogger(__name__)


class OpenRouterProvider(BaseAIProvider):
    """
    OpenRouter Provider — akses ratusan model via satu API key.
    Base URL: https://openrouter.ai/api/v1
    API Key prefix: sk-or-v1-
    Compatible dengan OpenAI SDK.

    Fitur:
    - Auto-retry dengan reduced max_tokens jika 402 (kredit kurang)
    - Fallback ke model gratis jika kredit habis total
    """

    DEFAULT_MODEL = "openai/gpt-5.5"

    # Model gratis sebagai fallback jika kredit habis
    FREE_FALLBACK_MODELS = [
        "google/gemini-2.5-flash",
        "deepseek/deepseek-chat-v3-0324",
        "meta-llama/llama-4-maverick",
        "qwen/qwen3-235b-a22b",
    ]

    def __init__(self, api_key: str, model: str = None):
        super().__init__(api_key)
        self.model = model or self.DEFAULT_MODEL
        self.client = OpenAI(
            api_key=api_key,
            base_url="https://openrouter.ai/api/v1",
            default_headers={
                "HTTP-Referer": "https://datanarasi.app",
                "X-OpenRouter-Title": "DataNarasi",
            }
        )

    def generate(self, prompt: str, system_prompt: str, max_tokens: int = 1024, model_id: str = None) -> str:
        """
        Generate narasi menggunakan OpenRouter API.
        Jika 402 (kredit kurang), otomatis:
        1. Retry dengan max_tokens lebih kecil
        2. Fallback ke model gratis

        Args:
            prompt: User prompt
            system_prompt: System prompt
            max_tokens: Maximum tokens
            model_id: Override model ID (dari admin panel DB)

        Returns:
            Generated text

        Raises:
            Exception: Jika semua retry gagal
        """
        active_model = model_id or self.model

        # Attempt 1: Model yang diminta, full max_tokens
        try:
            return self._call_api(active_model, prompt, system_prompt, max_tokens)
        except Exception as e:
            error_str = str(e)

            # Jika bukan error kredit/402, langsung raise
            if '402' not in error_str and 'credits' not in error_str.lower():
                raise

            logger.warning(f"⚠️  OpenRouter 402 (kredit kurang) dengan {active_model}, mencoba strategi fallback...")

        # Attempt 2: Model yang sama, max_tokens dikurangi
        reduced_tokens = min(max_tokens, 512)
        try:
            logger.info(f"Retry dengan max_tokens={reduced_tokens}...")
            return self._call_api(active_model, prompt, system_prompt, reduced_tokens)
        except Exception as e:
            error_str = str(e)
            if '402' not in error_str and 'credits' not in error_str.lower():
                raise
            logger.warning(f"⚠️  Masih 402 dengan {reduced_tokens} tokens, coba model gratis...")

        # Attempt 3: Fallback ke model gratis
        for fallback_model in self.FREE_FALLBACK_MODELS:
            try:
                logger.info(f"Fallback ke model gratis: {fallback_model}")
                return self._call_api(fallback_model, prompt, system_prompt, max_tokens)
            except Exception as e:
                logger.warning(f"Fallback {fallback_model} gagal: {str(e)}")
                continue

        # Semua gagal
        raise Exception(
            f"OpenRouter kredit habis dan semua fallback gagal. "
            f"Top up kredit di https://openrouter.ai/settings/credits"
        )

    def _call_api(self, model: str, prompt: str, system_prompt: str, max_tokens: int) -> str:
        """Panggil OpenRouter API untuk satu model"""
        logger.info(f"Using OpenRouter provider (model: {model}, max_tokens: {max_tokens})")

        response = self.client.chat.completions.create(
            model=model,
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": prompt}
            ],
            temperature=0.7,
            max_tokens=max_tokens,
        )

        narrative = response.choices[0].message.content

        # Strip <think>...</think> tags (beberapa model reasoning via OpenRouter)
        narrative = self._strip_thinking_tags(narrative)

        # Validate
        if not self.validate_narrative(narrative):
            raise ValueError("Generated narrative tidak memenuhi kriteria validasi")

        logger.info(f"✅ OpenRouter ({model}) success: {len(narrative)} chars generated")

        return narrative

    def _strip_thinking_tags(self, text: str) -> str:
        """
        Hapus tag <think>...</think> dari output reasoning models
        (DeepSeek R1, Qwen3, dll via OpenRouter)
        """
        cleaned = re.sub(r'<think>.*?</think>', '', text, flags=re.DOTALL)
        return cleaned.strip()


if __name__ == "__main__":
    # Test
    provider = OpenRouterProvider("test-key")
    print(f"OpenRouter provider initialized (model: {provider.model})")
