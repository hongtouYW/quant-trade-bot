"""AES-256-GCM Encryption Service for API Keys"""
import os
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
from flask import current_app


class EncryptionService:
    """Encrypts/decrypts sensitive data using AES-256-GCM with a master key."""

    @staticmethod
    def _get_key():
        key_hex = current_app.config.get('ENCRYPTION_MASTER_KEY', '')
        if not key_hex or len(key_hex) != 64:
            raise ValueError(
                f"ENCRYPTION_MASTER_KEY must be exactly 64 hex chars (32 bytes), got {len(key_hex)}"
            )
        return bytes.fromhex(key_hex)

    @staticmethod
    def encrypt(plaintext: str) -> tuple:
        """Encrypt plaintext string.

        Returns:
            (ciphertext: bytes, nonce: bytes)
        """
        key = EncryptionService._get_key()
        nonce = os.urandom(12)  # 96-bit nonce for GCM
        aesgcm = AESGCM(key)
        ciphertext = aesgcm.encrypt(nonce, plaintext.encode('utf-8'), None)
        return ciphertext, nonce

    @staticmethod
    def decrypt(ciphertext: bytes, nonce: bytes) -> str:
        """Decrypt ciphertext back to plaintext string."""
        key = EncryptionService._get_key()
        aesgcm = AESGCM(key)
        plaintext = aesgcm.decrypt(nonce, ciphertext, None)
        return plaintext.decode('utf-8')
