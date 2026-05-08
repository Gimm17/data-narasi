"""
TokenRouter Provider
Unified AI model hub — OpenAI-compatible API
Base URL: https://api.tokenrouter.com/v1
Docs: https://www.tokenrouter.com/docs
"""

import re
import logging
from openai import OpenAI
from .base import BaseAIProvider

logger = logging.getLogger(__name__)


class TokenRouterProvider(BaseAIProvider):
    """
    TokenRouter Provider — unified AI model hub with $200 Pro credit.
    Base URL: https://api.tokenrouter.com/v1
    API Key prefix: sk-
    Compatible dengan OpenAI SDK.

    Mendukung 57+ model text dari OpenAI, Anthropic, Google, DeepSeek,
    Qwen, xAI, MiniMax, Xiaomi, NVIDIA, Mistral, Z.AI, dll.
    """

    DEFAULT_MODEL = "deepseek/deepseek-v4-pro"

    def __init__(self, api_key: str, model: str = None):
        super().__init__(api_key)
        self.model = model or self.DEFAULT_MODEL
        self.client = OpenAI(
            api_key=api_key,
            base_url="https://api.tokenrouter.com/v1",
        )

    def generate(self, prompt: str, system_prompt: str, max_tokens: int = 1024, model_id: str = None) -> str:
        """
        Generate narasi menggunakan TokenRouter API

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
            logger.info(f"Using TokenRouter provider (model: {active_model})")

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

            # Strip <think>...</think> tags (reasoning models)
            narrative = self._strip_thinking_tags(narrative)

            # Validate
            if not self.validate_narrative(narrative):
                raise ValueError("Generated narrative tidak memenuhi kriteria validasi")

            logger.info(f"✅ TokenRouter ({active_model}) success: {len(narrative)} chars generated")

            return narrative

        except Exception as e:
            logger.error(f"TokenRouter error: {str(e)}")
            raise

    def _strip_thinking_tags(self, text: str) -> str:
        """Hapus tag <think>...</think> dari output reasoning models"""
        cleaned = re.sub(r'<think>.*?</think>', '', text, flags=re.DOTALL)
        return cleaned.strip()


if __name__ == "__main__":
    provider = TokenRouterProvider("test-key")
    print(f"TokenRouter provider initialized (model: {provider.model})")
