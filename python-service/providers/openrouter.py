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
    """

    DEFAULT_MODEL = "openai/gpt-5.5"

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
        Generate narasi menggunakan OpenRouter API

        Args:
            prompt: User prompt
            system_prompt: System prompt
            max_tokens: Maximum tokens
            model_id: Override model ID (dari admin panel DB)

        Returns:
            Generated text

        Raises:
            Exception: Jika gagal
        """
        try:
            active_model = model_id or self.model
            logger.info(f"Using OpenRouter provider (model: {active_model})")

            response = self.client.chat.completions.create(
                model=active_model,
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

            logger.info(f"✅ OpenRouter ({active_model}) success: {len(narrative)} chars generated")

            return narrative

        except Exception as e:
            logger.error(f"OpenRouter error: {str(e)}")
            raise

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
