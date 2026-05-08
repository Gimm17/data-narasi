"""
Claude Provider
Anthropic Claude (Sonnet 4)
"""

import logging
from anthropic import Anthropic
from .base import BaseAIProvider

logger = logging.getLogger(__name__)


class ClaudeProvider(BaseAIProvider):
    """
    Claude (Anthropic) Provider
    """

    def __init__(self, api_key: str):
        super().__init__(api_key)
        self.client = Anthropic(api_key=api_key)

    def generate(self, prompt: str, system_prompt: str, max_tokens: int = 1024, model_id: str = None) -> str:
        """
        Generate narasi menggunakan Claude API

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
            active_model = model_id or "claude-sonnet-4-20250514"
            logger.info(f"Using Claude provider (model: {active_model})")

            message = self.client.messages.create(
                model=active_model,
                max_tokens=max_tokens,
                temperature=0.7,
                system=system_prompt,
                messages=[
                    {"role": "user", "content": prompt}
                ]
            )

            narrative = message.content[0].text

            # Validate
            if not self.validate_narrative(narrative):
                raise ValueError("Generated narrative tidak memenuhi kriteria validasi")

            logger.info(f"✅ Claude success: {len(narrative)} chars generated")

            return narrative

        except Exception as e:
            logger.error(f"Claude error: {str(e)}")
            raise


if __name__ == "__main__":
    # Test
    provider = ClaudeProvider("test-key")
    print("Claude provider initialized")
