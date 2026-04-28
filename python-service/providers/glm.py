"""
GLM Provider
Z.AI GLM (OpenAI Compatible - api.z.ai)
"""

import logging
from openai import OpenAI
from .base import BaseAIProvider

logger = logging.getLogger(__name__)


class GLMProvider(BaseAIProvider):
    """
    GLM Provider via Z.AI API (OpenAI Compatible)
    Base URL: https://api.z.ai/api/coding/paas/v4
    """

    def __init__(self, api_key: str):
        super().__init__(api_key)
        self.client = OpenAI(
            api_key=api_key,
            base_url="https://api.z.ai/api/coding/paas/v4"
        )

    def generate(self, prompt: str, system_prompt: str, max_tokens: int = 1024) -> str:
        """
        Generate narasi menggunakan GLM API

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
            logger.info("Using GLM provider")

            # PENTING: Instruksi eksplisit untuk Bahasa Indonesia di AWAL prompt
            indo_instruction = "\n\nPENTING: Jawab HANYA dalam Bahasa Indonesia. Jangan gunakan bahasa Mandarin atau bahasa lain sama sekali."

            response = self.client.chat.completions.create(
                model="GLM-4.5-air",
                messages=[
                    {"role": "system", "content": system_prompt + indo_instruction},
                    {"role": "user", "content": prompt + indo_instruction}
                ],
                temperature=0.7,
                max_tokens=max_tokens
            )

            narrative = response.choices[0].message.content

            # Validate
            if not self.validate_narrative(narrative):
                raise ValueError("Generated narrative tidak memenuhi kriteria validasi")

            logger.info(f"✅ GLM success: {len(narrative)} chars generated")

            return narrative

        except Exception as e:
            logger.error(f"GLM error: {str(e)}")
            raise


if __name__ == "__main__":
    # Test
    provider = GLMProvider("test-key")
    print("GLM provider initialized")
