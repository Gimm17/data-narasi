"""
Kimi Provider
Moonshot AI API (api.moonshot.cn/v1)
"""

import logging
from openai import OpenAI
from .base import BaseAIProvider

logger = logging.getLogger(__name__)


class KimiProvider(BaseAIProvider):
    """
    Kimi (Moonshot AI) Provider - General API
    Menggunakan OpenAI SDK dengan base_url Moonshot
    """

    def __init__(self, api_key: str):
        super().__init__(api_key)
        self.client = OpenAI(
            api_key=api_key,
            base_url="https://api.moonshot.cn/v1"
        )

    def generate(self, prompt: str, system_prompt: str, max_tokens: int = 1024) -> str:
        """
        Generate narasi menggunakan Kimi API

        Args:
            prompt: User prompt
            system_prompt: System prompt
            max_tokens: Maximum tokens

        Returns:
            Generated text

        Raises:
            Exception: Jika gagal
        """
        try:
            logger.info("Using Kimi provider")

            response = self.client.chat.completions.create(
                model="moonshot-v1-8k",
                messages=[
                    {"role": "system", "content": system_prompt},
                    {"role": "user", "content": prompt}
                ],
                temperature=0.7,
                max_tokens=max_tokens
            )

            narrative = response.choices[0].message.content

            # Validate
            if not self.validate_narrative(narrative):
                raise ValueError("Generated narrative tidak memenuhi kriteria validasi")

            logger.info(f"✅ Kimi success: {len(narrative)} chars generated")

            return narrative

        except Exception as e:
            logger.error(f"Kimi error: {str(e)}")
            raise


if __name__ == "__main__":
    # Test
    provider = KimiProvider("test-key")
    # Note: perlu API key asli untuk test
    print("Kimi provider initialized")
