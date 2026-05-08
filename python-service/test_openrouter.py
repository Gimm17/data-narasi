"""
Quick test script for OpenRouter API integration.
Tests: API key validation, model listing, and narrative generation.
"""

import os
import sys
import json

# Read API key from environment variable (set in .env or Railway)
api_key = os.environ.get('OPENROUTER_API_KEY', '')
if not api_key:
    print("ERROR: OPENROUTER_API_KEY environment variable not set.")
    print("Set it with: $env:OPENROUTER_API_KEY='your-key-here'")
    sys.exit(1)
os.environ['OPENROUTER_API_KEY'] = api_key

# Add parent dir to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

def test_health_check():
    """Test 1: Validate API key via health checker"""
    print("=" * 60)
    print("TEST 1: Health Check (API Key Validation)")
    print("=" * 60)
    
    from health_checker import APIHealthChecker
    checker = APIHealthChecker()
    result = checker.check('openrouter', os.environ['OPENROUTER_API_KEY'])
    
    print(f"  Valid: {result['valid']}")
    print(f"  Models Count: {result['models_count']}")
    print(f"  Tier: {result['tier']}")
    print(f"  Response Time: {result['response_ms']}ms")
    if result['error']:
        print(f"  Error: {result['error']}")
    
    assert result['valid'], f"API key validation failed: {result['error']}"
    print("  ✅ PASSED\n")
    return result

def test_provider_init():
    """Test 2: Initialize OpenRouter provider"""
    print("=" * 60)
    print("TEST 2: Provider Initialization")
    print("=" * 60)
    
    from providers.openrouter import OpenRouterProvider
    provider = OpenRouterProvider(os.environ['OPENROUTER_API_KEY'])
    
    print(f"  Default Model: {provider.model}")
    print(f"  Client Base URL: {provider.client.base_url}")
    assert provider.model == "google/gemini-2.5-flash"
    print("  ✅ PASSED\n")
    return provider

def test_generate_narrative():
    """Test 3: Generate a short narrative"""
    print("=" * 60)
    print("TEST 3: Generate Narrative (Free Model)")
    print("=" * 60)
    
    from providers.openrouter import OpenRouterProvider
    provider = OpenRouterProvider(os.environ['OPENROUTER_API_KEY'])
    
    system_prompt = """Anda adalah analis data profesional. 
Tugas Anda adalah membuat narasi insight dari data dalam Bahasa Indonesia.
Gunakan angka dan fakta spesifik. Minimal 100 kata."""
    
    user_prompt = """Berikut ringkasan data penjualan Q1 2026:
- Total transaksi: 15.432
- Revenue: Rp 2.847.000.000
- Produk terlaris: Laptop Gaming (23% dari total)
- Pertumbuhan MoM: +12.5%
- Customer baru: 3.201
- Rata-rata nilai transaksi: Rp 184.500

Buatkan narasi insight yang mendalam dari data ini."""
    
    print("  Generating with model: google/gemini-2.5-flash ...")
    
    try:
        narrative = provider.generate(
            prompt=user_prompt,
            system_prompt=system_prompt,
            max_tokens=1024
        )
        
        word_count = len(narrative.split())
        print(f"  Generated: {len(narrative)} chars, {word_count} words")
        print(f"  Preview: {narrative[:200]}...")
        print("  ✅ PASSED\n")
        return narrative
    except Exception as e:
        print(f"  ❌ FAILED: {e}\n")
        raise

def test_dynamic_model():
    """Test 4: Generate with dynamic model_id override"""
    print("=" * 60)
    print("TEST 4: Dynamic Model Override")
    print("=" * 60)
    
    from providers.openrouter import OpenRouterProvider
    provider = OpenRouterProvider(os.environ['OPENROUTER_API_KEY'])
    
    # Use a different free model
    test_model = "deepseek/deepseek-chat-v3-0324"
    print(f"  Testing model override: {test_model}")
    
    try:
        narrative = provider.generate(
            prompt="Berikut data kinerja website DataNarasi di bulan April 2026: Total pengunjung 45.320, bounce rate 32%, rata-rata durasi 4 menit 12 detik, halaman terpopuler adalah Dashboard (28%), pengguna mobile 67%, konversi registrasi 8.5%, dan total file diproses 12.780 file. Buatkan narasi insight yang mendalam dari data ini dalam Bahasa Indonesia minimal 100 kata.",
            system_prompt="Anda adalah analis data profesional. Tugas Anda adalah membuat narasi insight dari data dalam Bahasa Indonesia. Gunakan angka dan fakta spesifik. Minimal 100 kata.",
            max_tokens=1024,
            model_id=test_model
        )
        
        word_count = len(narrative.split())
        print(f"  Generated: {len(narrative)} chars, {word_count} words")
        print(f"  Preview: {narrative[:200]}...")
        print("  ✅ PASSED\n")
        return narrative
    except Exception as e:
        print(f"  ❌ FAILED: {e}\n")
        raise

def test_ai_manager():
    """Test 5: Test through AIProviderManager with OpenRouter"""
    print("=" * 60)
    print("TEST 5: AIProviderManager Integration")
    print("=" * 60)
    
    from ai_provider import AIProviderManager
    manager = AIProviderManager()
    
    print(f"  Loaded providers: {list(manager.providers.keys())}")
    print(f"  Provider order: {manager.provider_order}")
    
    # Test with openrouter-only order (simulating admin panel)
    result = manager.generate(
        prompt="Berikut ringkasan data: Revenue Rp 500 juta, growth 15%, customer 2000 orang, produk terlaris Laptop (35%), rata-rata transaksi Rp 250.000, customer retention 78%. Buatkan narasi insight yang mendalam minimal 100 kata.",
        system_prompt="Anda adalah analis data profesional. Jawab dalam Bahasa Indonesia yang profesional dan mendalam. JANGAN mulai narasi dengan kata 'Berikut', 'Tentu', 'Baik', atau 'Tentu saja'. Langsung tulis narasi analisis.",
        max_tokens=1024,
        provider_order=[
            {"slug": "openrouter", "model_id": "google/gemini-2.5-flash"}
        ]
    )
    
    print(f"  Success: {result['success']}")
    print(f"  Provider Used: {result['provider_used']}")
    print(f"  Attempts: {result['attempts']}")
    print(f"  Processing Time: {result['processing_time_ms']}ms")
    print(f"  Narrative Length: {len(result['narrative'])} chars")
    
    if result['logs']:
        for log in result['logs']:
            status_icon = "✅" if log['status'] == 'success' else "❌"
            print(f"  {status_icon} Log: {log['provider_name']} → {log['status']}")
    
    assert result['success'], f"AI Manager failed: {result['logs']}"
    print("  ✅ PASSED\n")
    return result


if __name__ == "__main__":
    print("\n🚀 OpenRouter Integration Test Suite\n")
    
    try:
        test_health_check()
        test_provider_init()
        test_generate_narrative()
        test_dynamic_model()
        test_ai_manager()
        
        print("=" * 60)
        print("🎉 ALL TESTS PASSED!")
        print("=" * 60)
    except Exception as e:
        print("=" * 60)
        print(f"💥 TEST SUITE FAILED: {e}")
        print("=" * 60)
        sys.exit(1)
