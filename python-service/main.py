"""
DataNarasi Python Service
FastAPI service untuk data cleansing, analisis statistik, dan AI narrative generation
"""

from fastapi import FastAPI, BackgroundTasks, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Optional
import logging
import os
import hashlib
import hmac
import json
import time
from datetime import datetime
from pathlib import Path

# Load .env from parent directory (Laravel root) if not already in environment
try:
    from dotenv import load_dotenv
    # Look for .env in parent directory (Laravel root)
    env_path = Path(__file__).parent.parent / '.env'
    if env_path.exists():
        load_dotenv(dotenv_path=env_path, override=False)
        logging.getLogger(__name__).info(f"Loaded .env from: {env_path}")
    else:
        # Fallback: load from current directory
        load_dotenv(override=False)
except ImportError:
    pass  # python-dotenv not installed, rely on system env vars

# Import modules
from cleaner import DataCleaner
from analyzer import DataAnalyzer
from chart_generator import ChartGenerator
from prompt_builder import PromptBuilder
from ai_provider import AIProviderManager

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# FastAPI app
app = FastAPI(
    title="DataNarasi Python Service",
    description="Service untuk data cleansing, analisis, dan AI narrative generation",
    version="1.0.0"
)

# CORS middleware — baca allowed origins dari env, jangan wildcard di production
_allowed_origins_raw = os.getenv('ALLOWED_ORIGINS', 'http://localhost:8000')
ALLOWED_ORIGINS = [origin.strip() for origin in _allowed_origins_raw.split(',') if origin.strip()]
logger.info(f"CORS allowed origins: {ALLOWED_ORIGINS}")

app.add_middleware(
    CORSMiddleware,
    allow_origins=ALLOWED_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Callback retry configuration
CALLBACK_MAX_RETRIES = 3
CALLBACK_RETRY_BASE_DELAY = 2  # seconds, exponential: 2s, 4s, 8s


# Request models
class ProcessRequest(BaseModel):
    report_id: int
    file_path: str
    analysis_type: str
    tone: str
    callback_url: str
    callback_secret: Optional[str] = None
    provider_order: Optional[list] = None  # Dynamic order dari admin panel DB


class HealthResponse(BaseModel):
    status: str
    timestamp: str
    version: str


# Initialize components
cleaner = DataCleaner()
analyzer = DataAnalyzer()
chart_gen = ChartGenerator()
prompt_builder = PromptBuilder()
ai_manager = AIProviderManager()


def _generate_hmac_signature(payload: str, secret: str) -> str:
    """
    Generate HMAC-SHA256 signature dari JSON payload
    Digunakan untuk memvalidasi callback di Laravel side
    """
    return hmac.new(
        secret.encode('utf-8'),
        payload.encode('utf-8'),
        hashlib.sha256
    ).hexdigest()


def _send_callback_with_retry(url: str, data: dict, secret: str, max_retries: int = CALLBACK_MAX_RETRIES) -> bool:
    """
    Kirim callback ke Laravel dengan retry mechanism
    Exponential backoff: 2s, 4s, 8s

    Returns:
        True jika callback berhasil, False jika semua retry gagal
    """
    import httpx

    json_payload = json.dumps(data, default=str)

    # Generate HMAC signature dari payload
    hmac_signature = _generate_hmac_signature(json_payload, secret)

    headers = {
        'X-Callback-Secret': secret or '',
        'X-Callback-Signature': hmac_signature,
        'Content-Type': 'application/json',
    }

    for attempt in range(1, max_retries + 1):
        try:
            with httpx.Client(timeout=30.0) as client:
                response = client.post(
                    url,
                    content=json_payload,
                    headers=headers,
                )

                if response.status_code == 200:
                    logger.info(f"✅ Callback berhasil (attempt {attempt}/{max_retries})")
                    return True
                else:
                    logger.warning(
                        f"⚠️  Callback return {response.status_code} "
                        f"(attempt {attempt}/{max_retries}): {response.text[:200]}"
                    )

        except Exception as e:
            logger.warning(
                f"⚠️  Callback failed (attempt {attempt}/{max_retries}): {str(e)}"
            )

        # Retry dengan exponential backoff (kecuali attempt terakhir)
        if attempt < max_retries:
            delay = CALLBACK_RETRY_BASE_DELAY ** attempt  # 2s, 4s, 8s
            logger.info(f"⏳ Retrying callback in {delay}s...")
            time.sleep(delay)

    logger.error(f"❌ Callback gagal setelah {max_retries} attempts")
    return False


@app.get("/health", response_model=HealthResponse)
async def health_check():
    """
    Health check endpoint
    Dipanggil oleh Laravel untuk cek ketersediaan service
    """
    return HealthResponse(
        status="ok",
        timestamp=datetime.now().isoformat(),
        version="1.0.0"
    )


@app.post("/process", status_code=202)
async def process_data(request: ProcessRequest, background_tasks: BackgroundTasks):
    """
    Main endpoint untuk memproses data
    Menjalankan pipeline di background: cleansing → analisis → chart → AI narrative → callback
    Return 202 Accepted segera, proses berjalan di background
    """
    logger.info(f"Processing Report ID: {request.report_id} — dispatched to background")

    # Jalankan processing di background agar tidak timeout
    background_tasks.add_task(
        _process_report_background,
        request
    )

    return {
        "success": True,
        "message": "Processing started in background",
        "report_id": request.report_id
    }


def _process_report_background(request: ProcessRequest):
    """
    Background task: jalankan seluruh pipeline processing
    Dipanggil oleh BackgroundTasks agar /process endpoint tidak timeout
    """
    try:
        logger.info(f"[BG] Processing Report ID: {request.report_id}")
        logger.info(f"[BG] File: {request.file_path}")
        logger.info(f"[BG] Analysis Type: {request.analysis_type}")
        logger.info(f"[BG] Tone: {request.tone}")

        # Step 1: Data Cleansing
        logger.info("[BG] Step 1: Data Cleansing...")
        clean_result = cleaner.run(request.file_path)

        # Step 2: Statistical Analysis
        logger.info("[BG] Step 2: Statistical Analysis...")
        stats = analyzer.run(clean_result['df'], request.analysis_type)

        # Step 3: Chart Generation
        logger.info("[BG] Step 3: Chart Generation...")
        chart_paths = chart_gen.run(
            clean_result['df'],
            stats,
            request.report_id
        )

        # Step 4: Build Prompt
        logger.info("[BG] Step 4: Building Prompt...")
        system_prompt, user_prompt = prompt_builder.build(
            stats,
            clean_result['cleaning_log'],
            request.tone
        )

        # Step 5: Generate Narrative with AI
        logger.info("[BG] Step 5: Generating Narrative...")
        narrative_result = ai_manager.generate(
            user_prompt,
            system_prompt,
            max_tokens=1024,
            provider_order=request.provider_order
        )

        # Prepare callback data
        callback_data = {
            'status': 'success' if narrative_result['success'] else 'failed',
            'ai_narrative': narrative_result['narrative'] if narrative_result['success'] else None,
            'summary_stats': stats,
            'cleaning_log': clean_result['cleaning_log'],
            'chart_paths': chart_paths,
            'clean_path': clean_result.get('clean_path'),
            'clean_rows': len(clean_result['df']),
            'ai_provider_used': narrative_result['provider_used'],
            'processing_time_ms': narrative_result.get('processing_time_ms'),
            'ai_usage_logs': narrative_result.get('logs', [])
        }

        # Send callback ke Laravel dengan retry mechanism
        logger.info("[BG] Sending callback to Laravel...")
        success = _send_callback_with_retry(
            url=request.callback_url,
            data=callback_data,
            secret=request.callback_secret or '',
        )

        if success:
            logger.info(f"✅ Report {request.report_id} berhasil diproses dan callback terkirim!")
        else:
            logger.error(f"❌ Report {request.report_id} diproses tapi callback gagal setelah semua retry")

    except FileNotFoundError as e:
        logger.error(f"[BG] File not found: {request.file_path}")
        _send_error_callback(request, f"File not found: {str(e)}")

    except Exception as e:
        logger.error(f"[BG] Error processing Report {request.report_id}: {str(e)}")
        _send_error_callback(request, str(e))


def _send_error_callback(request: ProcessRequest, error_message: str):
    """Kirim callback error ke Laravel saat background processing gagal (with retry)"""
    error_data = {
        'status': 'failed',
        'ai_narrative': None,
        'summary_stats': {},
        'cleaning_log': {},
        'chart_paths': [],
        'clean_path': None,
        'clean_rows': 0,
        'ai_provider_used': None,
        'processing_time_ms': 0,
        'ai_usage_logs': [],
        'error_message': error_message
    }

    success = _send_callback_with_retry(
        url=request.callback_url,
        data=error_data,
        secret=request.callback_secret or '',
    )

    if not success:
        logger.error(f"[BG] Error callback also failed after all retries for Report {request.report_id}")


if __name__ == "__main__":
    import uvicorn

    # Run server
    uvicorn.run(
        "main:app",
        host="0.0.0.0",
        port=8001,
        reload=True,  # Development mode
        log_level="info"
    )
