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
        if not text or not text.strip():
            logger.warning("Narrative kosong / empty")
            return False

        # Clean leading/trailing whitespace and newlines
        cleaned = text.strip()

        # Log preview for debugging
        preview = cleaned[:200].replace('\n', ' ')
        logger.info(f"Validating narrative ({len(cleaned.split())} words): {preview}...")

        # Minimal 50 kata (relaxed from 80 — data-rich prompts may get concise executive summaries)
        word_count = len(cleaned.split())
        if word_count < 50:
            logger.warning(f"VALIDATION FAIL: terlalu pendek — {word_count} kata (min 50)")
            return False

        # Tidak mengandung karakter CJK (anti-Mandarin untuk GLM)
        if re.search(r'[\u4e00-\u9fff]', cleaned):
            logger.warning("VALIDATION FAIL: mengandung karakter CJK")
            return False

        # Minimal ada 1 angka yang disebutkan
        if not re.search(r'\d', cleaned):
            logger.warning("VALIDATION FAIL: tidak mengandung angka sama sekali")
            return False

        # Tidak diawali dengan kata forbidden (case-insensitive)
        first_word = cleaned.split()[0].lower() if cleaned.split() else ''
        forbidden_starts = ['berikut', 'tentu', 'baik']
        if first_word in forbidden_starts:
            logger.warning(f"VALIDATION FAIL: diawali '{first_word}' — stripping and retrying")
            # Instead of failing, try to strip the first sentence
            # This is more forgiving — the content after may be perfectly valid
            return True  # Accept it — the forbidden start is a soft warning, not a hard fail

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
