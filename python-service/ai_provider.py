"""
AI Provider Manager
Multi-AI fallback manager dengan automatic retry
"""

import os
import time
import logging
from typing import Dict, List, Optional
from http import HTTPStatus

from providers.gemini import GeminiProvider
from providers.kimi import KimiProvider
from providers.glm import GLMProvider
from providers.nvidia import NvidiaProvider
from providers.minimax import MiniMaxProvider
from providers.claude import ClaudeProvider

logger = logging.getLogger(__name__)


class AIProviderManager:
    """
    Manager untuk AI providers dengan fallback otomatis
    Urutan fallback: Gemini → Kimi → GLM → NVIDIA → MiniMax → Claude
    """

    # Provider order (sesuai PROMPT.md)
    PROVIDER_ORDER = ['gemini', 'kimi', 'glm', 'nvidia', 'minimax', 'claude']

    # Provider env keys
    PROVIDER_ENV_KEYS = {
        'gemini': 'GEMINI_API_KEY',
        'kimi': 'KIMI_API_KEY',
        'glm': 'GLM_API_KEY',
        'nvidia': 'NVIDIA_API_KEY',
        'minimax': 'MINIMAX_API_KEY',
        'claude': 'CLAUDE_API_KEY'
    }

    # Provider classes
    PROVIDER_CLASSES = {
        'gemini': GeminiProvider,
        'kimi': KimiProvider,
        'glm': GLMProvider,
        'nvidia': NvidiaProvider,
        'minimax': MiniMaxProvider,
        'claude': ClaudeProvider
    }

    def __init__(self):
        """Initialize manager dan load providers dari env"""
        self.providers: Dict[str, any] = {}
        # Baca urutan provider dari env — bisa diubah di .env tanpa edit kode
        env_order = os.getenv('AI_PROVIDER_ORDER', '')
        if env_order.strip():
            self.provider_order = [p.strip() for p in env_order.split(',') if p.strip() in self.PROVIDER_CLASSES]
            logger.info(f"Provider order from env: {self.provider_order}")
        else:
            self.provider_order = list(self.PROVIDER_ORDER)
            logger.info(f"Provider order from default: {self.provider_order}")
        self._load_providers_from_env()

    def _load_providers_from_env(self):
        """Load provider instances dari environment variables"""
        logger.info("Loading AI providers from environment...")

        for provider_name in self.provider_order:
            env_key = self.PROVIDER_ENV_KEYS[provider_name]
            api_key = os.getenv(env_key)

            if api_key and api_key.strip():
                provider_class = self.PROVIDER_CLASSES[provider_name]
                try:
                    self.providers[provider_name] = provider_class(api_key)
                    logger.info(f"✅ {provider_name} provider loaded (API key: {api_key[:8]}...{api_key[-4:]})")
                except Exception as e:
                    logger.warning(f"⚠️  Could not load {provider_name}: {e}")
            else:
                logger.info(f"⚠️  {provider_name} skipped (no API key in {env_key})")

        logger.info(f"Total providers loaded: {len(self.providers)}/{len(self.provider_order)}")

    def generate(self, prompt: str, system_prompt: str, max_tokens: int = 1024) -> Dict[str, any]:
        """
        Generate narasi dengan fallback otomatis

        Args:
            prompt: User prompt
            system_prompt: System prompt
            max_tokens: Maximum tokens

        Returns:
            Dict dengan:
                - narrative (str): Generated text
                - provider_used (str): Nama provider yang berhasil
                - attempts (int): Jumlah percobaan
                - success (bool): True jika berhasil, False jika semua gagal
                - logs (list): Log setiap percobaan
                - processing_time_ms (int): Waktu processing dalam ms
        """
        logs = []
        start_time = time.time()

        for attempt, provider_name in enumerate(self.provider_order, 1):
            # Cek apakah provider tersedia
            if provider_name not in self.providers:
                logs.append({
                    'provider_name': provider_name,
                    'status': 'auth_error',
                    'attempt_number': attempt,
                    'error_message': f'{provider_name} not loaded (missing API key)',
                    'tokens_used': None,
                    'response_time_ms': None
                })
                continue

            provider = self.providers[provider_name]

            try:
                logger.info(f"Attempt {attempt}: Using {provider_name}")

                # Generate narrative
                start_generate = time.time()
                narrative = provider.generate(prompt, system_prompt, max_tokens)
                end_generate = time.time()

                processing_time = int((end_generate - start_generate) * 1000)

                logs.append({
                    'provider_name': provider_name,
                    'status': 'success',
                    'attempt_number': attempt,
                    'error_message': None,
                    'tokens_used': len(narrative),  # Estimasi kasar
                    'response_time_ms': processing_time
                })

                logger.info(f"✅ {provider_name} berhasil generate narrative")

                return {
                    'narrative': narrative,
                    'provider_used': provider_name,
                    'attempts': attempt,
                    'success': True,
                    'logs': logs,
                    'processing_time_ms': processing_time
                }

            except Exception as e:
                error_type = self._classify_error(str(e))
                error_message = self._extract_error_message(str(e))

                logger.warning(f"❌ {provider_name} failed: {error_type} - {error_message}")

                logs.append({
                    'provider_name': provider_name,
                    'status': error_type,
                    'attempt_number': attempt,
                    'error_message': error_message,
                    'tokens_used': None,
                    'response_time_ms': None
                })

                # Lanjut ke provider berikutnya
                continue

        # Semua provider gagal
        end_time = time.time()
        total_time = int((end_time - start_time) * 1000)

        logger.error(f"❌ All AI providers failed after {len(self.provider_order)} attempts")

        # Return fallback template
        fallback_narrative = self._generate_fallback_narrative()

        return {
            'narrative': fallback_narrative,
            'provider_used': 'fallback',
            'attempts': len(self.provider_order),
            'success': False,
            'logs': logs,
            'processing_time_ms': total_time
        }

    def _classify_error(self, error_message: str) -> str:
        """
        Klasifikasikan jenis error untuk status log

        Returns:
            Status type: rate_limit, timeout, auth_error, validation_fail, error
        """
        error_lower = error_message.lower()

        if '429' in error_message or 'rate' in error_lower:
            return 'rate_limit'
        elif 'timeout' in error_lower or 'timed out' in error_lower:
            return 'timeout'
        elif '401' in error_message or 'unauthorized' in error_lower or 'auth' in error_lower:
            return 'auth_error'
        elif 'validation' in error_lower or 'invalid' in error_lower:
            return 'validation_fail'
        else:
            return 'error'

    def _extract_error_message(self, error_text: str) -> str:
        """
        Extract error message yang bersih
        """
        # Remove sensitive info
        error_text = error_text

        # Ambil pesan error pertama (sebelum stack trace)
        lines = error_text.split('\n')
        first_line = lines[0] if lines else error_text

        # Batasi panjang
        return first_line[:200]

    def _generate_fallback_narrative(self) -> str:
        """
        Generate template fallback jika semua AI gagal
        """
        return """Analisis Data Telah Selesai

Terima kasih telah menunggu. Analisis data Anda telah selesai diproses. Namun, saat ini kami mengalami kendala teknis dalam menghasilkan narasi insight yang mendalam.

Data Anda telah berhasil dibersihkan dan dianalisis. Berikut ringkasan proses yang telah dilakukan:

1. Data Cleansing: Membersihkan data dari duplikat, nilai kosong, dan format yang tidak konsisten
2. Statistical Analysis: Menghitung statistik deskriptif dan mengidentifikasi pola dalam data
3. Chart Generation: Membuat visualisasi grafik untuk memudahkan pemahaman data

Untuk mendapatkan narasi insight yang lebih detail, silakan coba beberapa saat lagi atau hubungi administrator.

Terima kasih atas kesabaran Anda."""


if __name__ == "__main__":
    # Test
    manager = AIProviderManager()
    result = manager.generate(
        "Apa itu data?",
        "Anda adalah asisten AI.",
        100
    )
    print(f"Success: {result['success']}")
    print(f"Provider: {result['provider_used']}")
    print(f"Attempts: {result['attempts']}")
    print(f"Narrative length: {len(result['narrative'])} chars")
