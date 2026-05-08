"""
API Health Checker
Per-provider API key validation dan quota/balance check
"""

import logging
import time
from datetime import datetime
from typing import Dict, Any, Optional

logger = logging.getLogger(__name__)


class APIHealthChecker:
    """
    Cek kesehatan API key untuk setiap provider.
    Validasi key + cek balance/quota jika provider mendukung.
    """

    TIMEOUT = 10  # seconds

    def check(self, slug: str, api_key: str, model_id: str = None) -> Dict[str, Any]:
        """
        Check API key health for a given provider.

        Returns dict with:
            valid: bool
            balance: float | None          (Kimi only)
            balance_detail: dict | None    (Kimi: voucher/cash breakdown)
            rate_limit: dict | None        (Claude: from response headers)
            tier: str | None               ('free' / 'premium' / None)
            models_count: int | None       (from /v1/models)
            error: str | None
            checked_at: str
        """
        start = time.time()
        result = {
            'valid': False,
            'balance': None,
            'balance_detail': None,
            'rate_limit': None,
            'tier': None,
            'models_count': None,
            'error': None,
            'response_ms': None,
            'checked_at': datetime.now().isoformat(),
        }

        checker_map = {
            'nvidia': self._check_openai_compatible,
            'gemini': self._check_gemini,
            'kimi': self._check_kimi,
            'glm': self._check_glm,
            'minimax': self._check_minimax,
            'claude': self._check_claude,
            'openrouter': self._check_openrouter,
            'tokenrouter': self._check_tokenrouter,
        }

        checker = checker_map.get(slug)
        if not checker:
            result['error'] = f'Unknown provider slug: {slug}'
            return result

        try:
            checker(api_key, model_id, result)
        except Exception as e:
            error_msg = str(e)[:300]
            # Redact API key from error messages
            if api_key and len(api_key) > 8:
                error_msg = error_msg.replace(api_key, '[REDACTED]')
            result['error'] = error_msg
            result['valid'] = False
            logger.error(f"Health check failed for {slug}: {error_msg}")

        result['response_ms'] = int((time.time() - start) * 1000)
        return result

    # ─── NVIDIA (OpenAI Compatible) ─────────────────────────

    def _check_openai_compatible(self, api_key: str, model_id: str, result: dict,
                                  base_url: str = "https://integrate.api.nvidia.com/v1"):
        """Check via OpenAI-compatible GET /v1/models endpoint"""
        import httpx

        headers = {
            'Authorization': f'Bearer {api_key}',
            'Content-Type': 'application/json',
        }

        with httpx.Client(timeout=self.TIMEOUT) as client:
            response = client.get(f"{base_url}/models", headers=headers)

            if response.status_code == 200:
                data = response.json()
                models = data.get('data', [])
                result['valid'] = True
                result['models_count'] = len(models)
                result['tier'] = 'free'  # NVIDIA NIM free tier
                logger.info(f"✅ NVIDIA key valid, {len(models)} models available")
            elif response.status_code == 401:
                result['error'] = 'API key tidak valid (401 Unauthorized)'
            elif response.status_code == 403:
                result['error'] = 'API key tidak punya akses (403 Forbidden)'
            else:
                result['error'] = f'Unexpected response: {response.status_code}'

    # ─── Gemini ─────────────────────────────────────────────

    def _check_gemini(self, api_key: str, model_id: str, result: dict):
        """Check via Google GenAI SDK models.list()"""
        try:
            from google import genai
            client = genai.Client(api_key=api_key)
            models_response = client.models.list()
            models = list(models_response)
            result['valid'] = True
            result['models_count'] = len(models)
            result['tier'] = 'free'  # Free tier by default
            logger.info(f"✅ Gemini key valid, {len(models)} models available")
        except ImportError:
            # Fallback: try old SDK
            try:
                import google.generativeai as genai_old
                genai_old.configure(api_key=api_key)
                models = list(genai_old.list_models())
                result['valid'] = True
                result['models_count'] = len(models)
                result['tier'] = 'free'
                logger.info(f"✅ Gemini key valid (legacy SDK), {len(models)} models")
            except Exception as e:
                raise e

    # ─── Kimi / Moonshot ────────────────────────────────────

    def _check_kimi(self, api_key: str, model_id: str, result: dict):
        """Check via OpenAI-compatible /v1/models + Kimi-specific balance API"""
        import httpx

        base_url = "https://api.moonshot.cn/v1"
        headers = {
            'Authorization': f'Bearer {api_key}',
            'Content-Type': 'application/json',
        }

        with httpx.Client(timeout=self.TIMEOUT) as client:
            # Step 1: Validate key via /v1/models
            response = client.get(f"{base_url}/models", headers=headers)

            if response.status_code == 200:
                data = response.json()
                models = data.get('data', [])
                result['valid'] = True
                result['models_count'] = len(models)
                logger.info(f"✅ Kimi key valid, {len(models)} models available")
            elif response.status_code == 401:
                result['error'] = 'API key tidak valid (401 Unauthorized)'
                return
            else:
                result['error'] = f'Unexpected response: {response.status_code}'
                return

            # Step 2: Check balance (Kimi-specific)
            try:
                balance_resp = client.get(
                    f"{base_url}/users/me/balance",
                    headers=headers
                )
                if balance_resp.status_code == 200:
                    balance_data = balance_resp.json()
                    bd = balance_data.get('data', {})
                    result['balance'] = bd.get('available_balance')
                    result['balance_detail'] = {
                        'available': bd.get('available_balance'),
                        'voucher': bd.get('voucher_balance'),
                        'cash': bd.get('cash_balance'),
                    }
                    # Infer tier from cash balance
                    cash = bd.get('cash_balance', 0)
                    result['tier'] = 'premium' if cash > 0 else 'free'
                    logger.info(f"✅ Kimi balance: ¥{result['balance']}")
            except Exception as e:
                logger.warning(f"Kimi balance check failed: {e}")

    # ─── GLM / ZhiPu ───────────────────────────────────────

    def _check_glm(self, api_key: str, model_id: str, result: dict):
        """Check via OpenAI-compatible /models endpoint"""
        self._check_openai_compatible(
            api_key, model_id, result,
            base_url="https://api.z.ai/api/coding/paas/v4"
        )
        if result['valid']:
            result['tier'] = None  # GLM doesn't expose tier info
            logger.info("✅ GLM key valid")

    # ─── MiniMax ────────────────────────────────────────────

    def _check_minimax(self, api_key: str, model_id: str, result: dict):
        """Check via OpenAI-compatible /v1/models + Token Plan remains"""
        import httpx

        base_url = "https://api.minimax.io/v1"
        headers = {
            'Authorization': f'Bearer {api_key}',
            'Content-Type': 'application/json',
        }

        with httpx.Client(timeout=self.TIMEOUT) as client:
            # Step 1: Validate key via /v1/models
            response = client.get(f"{base_url}/models", headers=headers)

            if response.status_code == 200:
                data = response.json()
                models = data.get('data', [])
                result['valid'] = True
                result['models_count'] = len(models)
                logger.info(f"✅ MiniMax key valid, {len(models)} models available")
            elif response.status_code == 401:
                result['error'] = 'API key tidak valid (401 Unauthorized)'
                return
            else:
                result['error'] = f'Unexpected response: {response.status_code}'
                return

            # Infer tier from key prefix
            if api_key.startswith('sk-cp-'):
                result['tier'] = 'coding_plan'
            elif api_key.startswith('sk-api-'):
                result['tier'] = 'standard'
            else:
                result['tier'] = 'unknown'

            # Step 2: Try Token Plan balance (only for coding plan keys)
            if api_key.startswith('sk-cp-'):
                try:
                    tp_headers = {
                        'Authorization': f'Bearer {api_key}',
                        'Content-Type': 'application/json',
                    }
                    tp_resp = client.get(
                        "https://www.minimax.io/v1/token_plan/remains",
                        headers=tp_headers
                    )
                    if tp_resp.status_code == 200:
                        tp_data = tp_resp.json()
                        result['balance_detail'] = tp_data
                        logger.info(f"✅ MiniMax Token Plan balance retrieved")
                except Exception as e:
                    logger.warning(f"MiniMax Token Plan check failed: {e}")

    # ─── Claude / Anthropic ─────────────────────────────────

    def _check_claude(self, api_key: str, model_id: str, result: dict):
        """Check via lightweight messages call, capture rate limit headers"""
        import httpx

        headers = {
            'x-api-key': api_key,
            'anthropic-version': '2023-06-01',
            'content-type': 'application/json',
        }

        # Use a tiny request to validate key and get rate limit headers
        payload = {
            'model': model_id or 'claude-3-haiku-20240307',
            'max_tokens': 1,
            'messages': [
                {'role': 'user', 'content': 'Hi'}
            ]
        }

        with httpx.Client(timeout=self.TIMEOUT) as client:
            response = client.post(
                "https://api.anthropic.com/v1/messages",
                headers=headers,
                json=payload
            )

            if response.status_code in (200, 201):
                result['valid'] = True

                # Parse rate limit headers
                rl = {}
                header_map = {
                    'requests_limit': 'anthropic-ratelimit-requests-limit',
                    'requests_remaining': 'anthropic-ratelimit-requests-remaining',
                    'input_tokens_limit': 'anthropic-ratelimit-input-tokens-limit',
                    'input_tokens_remaining': 'anthropic-ratelimit-input-tokens-remaining',
                    'output_tokens_limit': 'anthropic-ratelimit-output-tokens-limit',
                    'output_tokens_remaining': 'anthropic-ratelimit-output-tokens-remaining',
                }
                for key, header in header_map.items():
                    val = response.headers.get(header)
                    if val is not None:
                        try:
                            rl[key] = int(val)
                        except ValueError:
                            rl[key] = val

                if rl:
                    result['rate_limit'] = rl

                logger.info(f"✅ Claude key valid, rate limits captured")

            elif response.status_code == 401:
                result['error'] = 'API key tidak valid (401 Unauthorized)'
            elif response.status_code == 403:
                result['error'] = 'API key tidak punya akses (403 Forbidden)'
            elif response.status_code == 429:
                # Key is valid but rate limited
                result['valid'] = True
                result['error'] = 'Rate limited — coba lagi nanti'
                result['rate_limit'] = {'status': 'rate_limited'}
            else:
                # Try to parse error
                try:
                    err = response.json()
                    result['error'] = err.get('error', {}).get('message', f'Status {response.status_code}')
                except Exception:
                    result['error'] = f'Unexpected response: {response.status_code}'

    # ─── OpenRouter ─────────────────────────────────────────

    def _check_openrouter(self, api_key: str, model_id: str, result: dict):
        """Check via OpenRouter /api/v1/models endpoint"""
        import httpx

        headers = {
            'Authorization': f'Bearer {api_key}',
            'Content-Type': 'application/json',
        }

        with httpx.Client(timeout=self.TIMEOUT) as client:
            response = client.get(
                "https://openrouter.ai/api/v1/models",
                headers=headers
            )

            if response.status_code == 200:
                data = response.json()
                models = data.get('data', [])
                result['valid'] = True
                result['models_count'] = len(models)
                result['tier'] = 'free'  # OpenRouter has free models
                logger.info(f"✅ OpenRouter key valid, {len(models)} models available")
            elif response.status_code == 401:
                result['error'] = 'API key tidak valid (401 Unauthorized)'
            elif response.status_code == 403:
                result['error'] = 'API key tidak punya akses (403 Forbidden)'
            else:
                result['error'] = f'Unexpected response: {response.status_code}'

    # ─── TokenRouter ─────────────────────────────────────────

    def _check_tokenrouter(self, api_key: str, model_id: str, result: dict):
        """Check via TokenRouter /v1/models endpoint"""
        import httpx

        headers = {
            'Authorization': f'Bearer {api_key}',
            'Content-Type': 'application/json',
        }

        with httpx.Client(timeout=self.TIMEOUT) as client:
            response = client.get(
                "https://api.tokenrouter.com/v1/models",
                headers=headers
            )

            if response.status_code == 200:
                data = response.json()
                models = data.get('data', [])
                # Count text-capable models only
                text_models = [m for m in models if 'openai' in m.get('supported_endpoint_types', [])]
                result['valid'] = True
                result['models_count'] = len(text_models)
                result['tier'] = 'pro'
                logger.info(f"✅ TokenRouter key valid, {len(text_models)} text models available")
            elif response.status_code == 401:
                result['error'] = 'API key tidak valid (401 Unauthorized)'
            elif response.status_code == 403:
                result['error'] = 'API key tidak punya akses (403 Forbidden)'
            else:
                result['error'] = f'Unexpected response: {response.status_code}'
