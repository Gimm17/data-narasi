# python-service/providers/nvidia.py
"""
NVIDIA NIM Provider
NVIDIA API Catalog (integrate.api.nvidia.com/v1) — OpenAI Compatible
Dokumentasi: https://build.nvidia.com/models
"""

import logging
from openai import OpenAI
from .base import BaseAIProvider

logger = logging.getLogger(__name__)


class NvidiaProvider(BaseAIProvider):
    """
    NVIDIA NIM Provider via API Catalog (OpenAI Compatible)
    Base URL: https://integrate.api.nvidia.com/v1
    Free tier: 40 RPM, tanpa expiry untuk development/testing
    """

    # Model default — bisa diganti sesuai kebutuhan dari NVIDIA API Catalog
    DEFAULT_MODEL = "meta/llama-3.1-8b-instruct"

    # Model alternatif yang tersedia di NVIDIA API Catalog (gratis)
    AVAILABLE_MODELS = [
        "meta/llama-3.1-8b-instruct",       # Cepat, general purpose
        "meta/llama-3.1-70b-instruct",       # Lebih kuat, reasoning lebih baik
        "meta/llama-3.3-70b-instruct",       # Terbaru dari Meta
        "deepseek-ai/deepseek-r1",           # Reasoning model
        "nvidia/nemotron-mini-4b-instruct",  # NVIDIA native, sangat cepat
    ]

    def __init__(self, api_key: str, model: str = None):
        super().__init__(api_key)
        self.model = model or self.DEFAULT_MODEL
        self.client = OpenAI(
            api_key=api_key,
            base_url="https://integrate.api.nvidia.com/v1"
        )

    def generate(self, prompt: str, system_prompt: str, max_tokens: int = 1024) -> str:
        """
        Generate narasi menggunakan NVIDIA NIM API

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
            logger.info(f"Using NVIDIA NIM provider (model: {self.model})")

            response = self.client.chat.completions.create(
                model=self.model,
                messages=[
                    {"role": "system", "content": system_prompt},
                    {"role": "user", "content": prompt}
                ],
                temperature=0.7,
                top_p=1,
                max_tokens=max_tokens
            )

            narrative = response.choices[0].message.content

            # Validate
            if not self.validate_narrative(narrative):
                raise ValueError("Generated narrative tidak memenuhi kriteria validasi")

            logger.info(f"✅ NVIDIA NIM success: {len(narrative)} chars generated")

            return narrative

        except Exception as e:
            logger.error(f"NVIDIA NIM error: {str(e)}")
            raise


if __name__ == "__main__":
    # Test
    provider = NvidiaProvider("test-key")
    # Note: perlu API key asli untuk test
    print(f"NVIDIA NIM provider initialized (model: {provider.model})")
    print(f"Available models: {provider.AVAILABLE_MODELS}")
