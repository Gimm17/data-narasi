# python-service/providers/minimax.py
"""
MiniMax Provider
MiniMax API (api.minimax.io/v1) — OpenAI Compatible
Dokumentasi: https://platform.minimax.io/
"""

import re
import logging
from openai import OpenAI
from .base import BaseAIProvider

logger = logging.getLogger(__name__)


class MiniMaxProvider(BaseAIProvider):
    """
    MiniMax Provider via OpenAI-Compatible API
    Base URL: https://api.minimax.io/v1
    Model: MiniMax-M2.5 (default), MiniMax-M2.7 (terbaru)
    API Key prefix: sk-cp- (Coding Plan) atau sk-api- (Standard)
    """

    # Model default — stabil dan cepat untuk production
    DEFAULT_MODEL = "MiniMax-M2.5"

    # Model alternatif yang tersedia di MiniMax
    AVAILABLE_MODELS = [
        "MiniMax-M2.5",             # Stabil, cepat, bagus untuk coding & agent
        "MiniMax-M2.5-highspeed",   # Versi cepat dari M2.5
        "MiniMax-M2.7",             # Terbaru, reasoning & coding lebih baik
        "MiniMax-M2.7-highspeed",   # Versi cepat dari M2.7
    ]

    def __init__(self, api_key: str, model: str = None):
        super().__init__(api_key)
        self.model = model or self.DEFAULT_MODEL
        self.client = OpenAI(
            api_key=api_key,
            base_url="https://api.minimax.io/v1"
        )

    def generate(self, prompt: str, system_prompt: str, max_tokens: int = 1024, model_id: str = None) -> str:
        """
        Generate narasi menggunakan MiniMax API

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
            active_model = model_id or self.model
            logger.info(f"Using MiniMax provider (model: {active_model})")

            response = self.client.chat.completions.create(
                model=active_model,
                messages=[
                    {"role": "system", "content": system_prompt},
                    {"role": "user", "content": prompt}
                ],
                temperature=0.7,
                top_p=0.9,
                max_tokens=max_tokens
            )

            narrative = response.choices[0].message.content

            # Hapus tag <think>...</think> dari reasoning tokens MiniMax
            narrative = self._strip_thinking_tags(narrative)

            # Validate
            if not self.validate_narrative(narrative):
                raise ValueError("Generated narrative tidak memenuhi kriteria validasi")

            logger.info(f"✅ MiniMax success: {len(narrative)} chars generated")

            return narrative

        except Exception as e:
            logger.error(f"MiniMax error: {str(e)}")
            raise

    def _strip_thinking_tags(self, text: str) -> str:
        """
        Hapus tag <think>...</think> dari output MiniMax
        MiniMax M2.5/M2.7 bisa mengembalikan reasoning tokens
        yang dibungkus dalam tag ini
        """
        # Hapus <think>...</think> beserta isinya (termasuk multiline)
        cleaned = re.sub(r'<think>.*?</think>', '', text, flags=re.DOTALL)
        # Bersihkan whitespace berlebih di awal/akhir
        return cleaned.strip()


if __name__ == "__main__":
    # Test
    provider = MiniMaxProvider("test-key")
    # Note: perlu API key asli untuk test
    print(f"MiniMax provider initialized (model: {provider.model})")
    print(f"Available models: {provider.AVAILABLE_MODELS}")
