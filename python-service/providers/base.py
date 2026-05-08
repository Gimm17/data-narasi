"""
Base AI Provider
Abstract class untuk semua AI provider implementations
"""

from abc import ABC, abstractmethod
from typing import Dict, Any, Optional
import logging
import re

logger = logging.getLogger(__name__)


class BaseAIProvider(ABC):
    """
    Base class untuk AI Provider
    Semua provider harus inherit dari ini dan override generate() method
    """

    def __init__(self, api_key: str):
        self.api_key = api_key
        self.max_tokens = 1024
        self.timeout_seconds = 30
        # Token usage dari panggilan terakhir — diisi oleh setiap provider
        self._last_usage = {
            'prompt_tokens': 0,
            'completion_tokens': 0,
            'total_tokens': 0,
            'model_used': None,  # model yang benar-benar dipakai (bisa fallback)
        }

    @abstractmethod
    def generate(self, prompt: str, system_prompt: str, max_tokens: int = 1024, model_id: str = None) -> str:
        """
        Generate narasi dari prompt

        Args:
            prompt: User prompt
            system_prompt: System prompt
            max_tokens: Maximum tokens untuk generate

        Returns:
            Generated text (narasi)

        Raises:
            Exception: Jika gagal generate
        """
        pass

    def validate_narrative(self, text: str) -> bool:
        """
        Validasi output narasi (sesuai SKILL.md)

        Returns:
            True jika valid, False jika tidak
        """
        # Minimal 80 kata
        word_count = len(text.split())
        if word_count < 80:
            logger.warning(f"Narrative terlalu pendek: {word_count} kata")
            return False

        # Tidak mengandung karakter CJK (anti-Mandarin untuk GLM)
        if re.search(r'[\u4e00-\u9fff]', text):
            logger.warning("Narrative mengandung karakter CJK (CJK characters)")
            return False

        # Minimal ada 1 angka yang disebutkan
        if not re.search(r'\d', text):
            logger.warning("Narrative tidak mengandung angka")
            return False

        # Tidak diawali dengan "Berikut" / "Tentu" / "Baik"
        forbidden_starts = ['Berikut', 'Tentu', 'Baik', 'Berikut ini', 'Tentu saja']
        for start in forbidden_starts:
            if text.strip().startswith(start):
                logger.warning(f"Narrative diawali dengan forbidden word: {start}")
                return False

        return True

    def _extract_error_message(self, error_text: str) -> str:
        """
        Extract error message dari error response

        Args:
            error_text: Raw error text

        Returns:
            Cleaned error message
        """
        # Remove API keys if present
        error_text = re.sub(r'AIza[A-Za-z0-9\-_]+', '[REDACTED]', error_text)

        # Take first 200 chars
        return error_text[:200]
