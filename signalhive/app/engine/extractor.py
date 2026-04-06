"""
Extractor: Layer 2 of the signal extraction pipeline.
Uses Claude Haiku for structured signal extraction.
Cost ~$0.01-0.03 per call.
"""
import json
import logging
import os
from typing import Optional

logger = logging.getLogger(__name__)

EXTRACTION_SYSTEM_PROMPT = """You are a crypto trading signal extractor. Analyze the message and extract
trading signals if present. Return a JSON object or null if no actionable signal found.

Rules:
- Only extract explicit or strongly implied trading signals
- Confidence 0.0-1.0 based on how specific and actionable the signal is
- Set TTL based on timeframe: scalp=900, intraday=3600, swing=86400
- Always preserve the original source text exactly
- coin should be the ticker symbol (e.g. BTC, ETH, SOL)
- direction must be LONG or SHORT
- entry_hint, tp_hint, sl_hint should be price strings or null
- reasoning should be a brief explanation

Return ONLY valid JSON matching this schema:
{
  "coin": "BTC",
  "direction": "LONG",
  "confidence": 0.85,
  "entry_hint": "68000",
  "tp_hint": "72000",
  "sl_hint": "66000",
  "reasoning": "Strong breakout above resistance",
  "ttl_seconds": 3600,
  "source_text": "original message here"
}

If no signal found, return exactly: null"""


def extract_signal(text: str, api_key: str = None) -> Optional[dict]:
    """
    Extract trading signal from message text using Claude Haiku.
    Returns dict with signal data or None.
    """
    if not api_key:
        api_key = os.environ.get('ANTHROPIC_API_KEY', '')

    if not api_key:
        logger.warning("No ANTHROPIC_API_KEY configured, skipping LLM extraction")
        return None

    try:
        import anthropic
        client = anthropic.Anthropic(api_key=api_key)

        response = client.messages.create(
            model="claude-haiku-4-5-20251001",
            max_tokens=500,
            system=EXTRACTION_SYSTEM_PROMPT,
            messages=[
                {"role": "user", "content": f"Extract trading signal from this message:\n\n{text}"}
            ]
        )

        content = response.content[0].text.strip()

        if content.lower() == 'null' or content == '{}':
            return None

        # Parse JSON
        result = json.loads(content)

        # Validate required fields
        required = ['coin', 'direction', 'confidence', 'reasoning', 'source_text']
        for field in required:
            if field not in result:
                logger.warning(f"Missing required field: {field}")
                return None

        # Normalize
        result['coin'] = result['coin'].upper().strip()
        result['direction'] = result['direction'].upper().strip()
        if result['direction'] not in ('LONG', 'SHORT'):
            return None

        result['confidence'] = max(0.0, min(1.0, float(result['confidence'])))
        result['ttl_seconds'] = int(result.get('ttl_seconds', 3600))

        return result

    except json.JSONDecodeError as e:
        logger.error(f"Failed to parse LLM response as JSON: {e}")
        return None
    except Exception as e:
        logger.error(f"LLM extraction error: {e}")
        return None
