"""Tests for EncryptionService (AES-256-GCM)."""
import pytest
from app.services.encryption_service import EncryptionService


class TestEncryptionService:

    def test_encrypt_decrypt_roundtrip(self, app_ctx):
        """Encrypting then decrypting should return the original text."""
        plaintext = 'my-secret-api-key-12345'
        ciphertext, nonce = EncryptionService.encrypt(plaintext)
        result = EncryptionService.decrypt(ciphertext, nonce)
        assert result == plaintext

    def test_encrypt_produces_different_nonces(self, app_ctx):
        """Each encryption should use a unique nonce."""
        plaintext = 'same-key'
        _, nonce1 = EncryptionService.encrypt(plaintext)
        _, nonce2 = EncryptionService.encrypt(plaintext)
        assert nonce1 != nonce2

    def test_encrypt_produces_different_ciphertexts(self, app_ctx):
        """Different nonces should produce different ciphertexts."""
        plaintext = 'same-key'
        ct1, _ = EncryptionService.encrypt(plaintext)
        ct2, _ = EncryptionService.encrypt(plaintext)
        assert ct1 != ct2

    def test_decrypt_wrong_nonce_fails(self, app_ctx):
        """Decryption with wrong nonce should fail."""
        plaintext = 'my-api-key'
        ciphertext, _ = EncryptionService.encrypt(plaintext)
        wrong_nonce = b'\x00' * 12
        with pytest.raises(Exception):
            EncryptionService.decrypt(ciphertext, wrong_nonce)

    def test_decrypt_tampered_ciphertext_fails(self, app_ctx):
        """Tampered ciphertext should fail authentication."""
        plaintext = 'my-api-key'
        ciphertext, nonce = EncryptionService.encrypt(plaintext)
        tampered = bytes([b ^ 0xFF for b in ciphertext[:4]]) + ciphertext[4:]
        with pytest.raises(Exception):
            EncryptionService.decrypt(tampered, nonce)

    def test_encrypt_empty_string(self, app_ctx):
        """Should handle empty string."""
        ct, nonce = EncryptionService.encrypt('')
        result = EncryptionService.decrypt(ct, nonce)
        assert result == ''

    def test_encrypt_unicode(self, app_ctx):
        """Should handle unicode characters."""
        plaintext = '测试API密钥🔑'
        ct, nonce = EncryptionService.encrypt(plaintext)
        result = EncryptionService.decrypt(ct, nonce)
        assert result == plaintext

    def test_invalid_master_key_raises(self, app):
        """Invalid master key should raise ValueError."""
        with app.app_context():
            app.config['ENCRYPTION_MASTER_KEY'] = 'short'
            with pytest.raises(ValueError, match='64 hex chars'):
                EncryptionService.encrypt('test')
            # Restore
            app.config['ENCRYPTION_MASTER_KEY'] = 'a' * 64

    def test_nonce_is_12_bytes(self, app_ctx):
        """Nonce should be 96-bit (12 bytes) for GCM."""
        _, nonce = EncryptionService.encrypt('test')
        assert len(nonce) == 12
