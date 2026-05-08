"""
Gemini Provider
Google Gemini via new google.genai SDK
"""

import os
import logging
from .base import BaseAIProvider

logger = logging.getLogger(__name__)


class GeminiProvider(BaseAIProvider):
    """
    Gemini Provider menggunakan google.genai SDK (baru)
    """

    def __init__(self, api_key: str):
        super().__init__(api_key)
        try:
            from google import genai
            self.client = genai.Client(api_key=api_key)
            self._sdk = 'new'
        except (ImportError, AttributeError):
            # Fallback ke SDK lama jika google.genai belum tersedia
            import google.generativeai as genai_old
            genai_old.configure(api_key=api_key)
            self._genai = genai_old
            self._sdk = 'old'

    def generate(self, prompt: str, system_prompt: str, max_tokens: int = 1024, model_id: str = None) -> str:
        """
        Generate narasi menggunakan Gemini API

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
            active_model = model_id or "gemini-2.5-flash"
            logger.info(f"Using Gemini provider (model: {active_model})")

            if self._sdk == 'new':
                from google.genai import types

                response = self.client.models.generate_content(
                    model=active_model,
                    config=types.GenerateContentConfig(
                        system_instruction=system_prompt,
                        max_output_tokens=max_tokens,
                        temperature=0.7,
                    ),
                    contents=prompt
                )
                narrative = response.text
            else:
                # Fallback: SDK lama
                model = self._genai.GenerativeModel(
                    active_model,
                    generation_config=self._genai.GenerationConfig(
                        max_output_tokens=max_tokens,
                        temperature=0.7
                    )
                )
                full_prompt = f"{system_prompt}\n\n{prompt}"
                response = model.generate_content(full_prompt)
                narrative = response.text

            # Validate
            if not self.validate_narrative(narrative):
                raise ValueError("Generated narrative tidak memenuhi kriteria validasi")

            logger.info(f"✅ Gemini success: {len(narrative)} chars generated")

            return narrative

        except Exception as e:
            logger.error(f"Gemini error: {str(e)}")
            raise


if __name__ == "__main__":
    # Test
    provider = GeminiProvider(os.getenv('GEMINI_API_KEY'))
    result = provider.generate(
        "Apa itu data?",
        "Anda adalah asisten AI.",
        100
    )
    print(result)
