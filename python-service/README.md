# DataNarasi Python Service

Service Python untuk data cleansing, analisis statistik, dan AI narrative generation.

## Requirements

- Python 3.11+
- pip atau virtual environment

## Installation

1. Install dependencies:
```bash
cd python-service
pip install -r requirements.txt
```

2. Setup environment variables:
```bash
cp .env.example .env
```

Edit `.env` dan isi API keys yang diperlukan.

## Running

### Development mode:
```bash
uvicorn main:app --reload --port 8001
```

### Production:
```bash
uvicorn main:app --host 0.0.0.0 --port $PORT
```

## API Endpoints

### GET /health
Health check endpoint.

### POST /process
Main endpoint untuk memproses data.

Request body:
```json
{
  "report_id": 123,
  "file_path": "/path/to/file.csv",
  "analysis_type": "penjualan",
  "tone": "formal",
  "callback_url": "https://datanarasi.app/api/v1/reports/123/callback",
  "callback_secret": "secret-token"
}
```

## Modules

- **main.py**: FastAPI app dan endpoints
- **cleaner.py**: Data cleansing dengan Pandas
- **analyzer.py**: Statistik dan tren
- **chart_generator.py**: Matplotlib charts
- **prompt_builder.py**: Prompt builder untuk AI
- **ai_provider.py**: Multi-AI fallback manager
- **providers/**: Individual AI provider implementations

## AI Provider Fallback

Urutan fallback (otomatis):
1. Gemini 1.5 Flash (Google) - Free tier
2. Kimi (Moonshot AI)
3. GLM-4 Flash (Zhipu AI)
4. Claude (Anthropic) - Paid (disabled by default)

## Troubleshooting

### ImportError: No module named 'google.generativeai'
```bash
pip install google-generativeai
```

### ModuleNotFoundError
Pastikan semua dependencies terinstall:
```bash
pip install -r requirements.txt
```

### Port 8001 already in use
```bash
# Cari proses yang menggunakan port 8001
netstat -ano | findstr :8001
# Kill proses tersebut
```

## Testing

Test health endpoint:
```bash
curl http://localhost:8001/health
```

Expected response:
```json
{
  "status": "ok",
  "timestamp": "2025-03-23T...",
  "version": "1.0.0"
}
```
