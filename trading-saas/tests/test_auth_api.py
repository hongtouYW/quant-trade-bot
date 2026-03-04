"""Integration tests for Auth API."""
import pytest


class TestAdminLogin:

    def test_admin_login_success(self, client, admin):
        resp = client.post('/api/auth/admin/login', json={
            'username': 'testadmin',
            'password': 'password123',
        })
        assert resp.status_code == 200
        data = resp.get_json()
        assert 'access_token' in data
        assert 'refresh_token' in data
        assert data['user']['username'] == 'testadmin'

    def test_admin_login_wrong_password(self, client, admin):
        resp = client.post('/api/auth/admin/login', json={
            'username': 'testadmin',
            'password': 'wrongpassword',
        })
        assert resp.status_code == 401

    def test_admin_login_nonexistent_user(self, client, admin):
        resp = client.post('/api/auth/admin/login', json={
            'username': 'nouser',
            'password': 'password123',
        })
        assert resp.status_code == 401

    def test_admin_login_missing_fields(self, client, admin):
        resp = client.post('/api/auth/admin/login', json={
            'username': 'testadmin',
        })
        assert resp.status_code == 400


class TestAgentLogin:

    def test_agent_login_success(self, client, agent):
        resp = client.post('/api/auth/agent/login', json={
            'username': 'testagent',
            'password': 'password123',
        })
        assert resp.status_code == 200
        data = resp.get_json()
        assert 'access_token' in data
        assert data['user']['username'] == 'testagent'

    def test_agent_login_wrong_password(self, client, agent):
        resp = client.post('/api/auth/agent/login', json={
            'username': 'testagent',
            'password': 'wrongpassword',
        })
        assert resp.status_code == 401


class TestAgentRegister:

    def test_register_success(self, client, admin):
        resp = client.post('/api/auth/agent/register', json={
            'username': 'newagent',
            'email': 'new@test.com',
            'password': 'password123',
            'display_name': 'New Agent',
        })
        assert resp.status_code == 201
        data = resp.get_json()
        assert data['username'] == 'newagent'
        assert 'approval' in data['message'].lower() or 'admin' in data['message'].lower()

    def test_register_duplicate_username(self, client, agent):
        resp = client.post('/api/auth/agent/register', json={
            'username': 'testagent',  # Already exists
            'email': 'other@test.com',
            'password': 'password123',
        })
        assert resp.status_code == 409

    def test_register_duplicate_email(self, client, agent):
        resp = client.post('/api/auth/agent/register', json={
            'username': 'otheragent',
            'email': 'agent@test.com',  # Already exists
            'password': 'password123',
        })
        assert resp.status_code == 409

    def test_register_short_username(self, client, admin):
        resp = client.post('/api/auth/agent/register', json={
            'username': 'ab',
            'email': 'ab@test.com',
            'password': 'password123',
        })
        assert resp.status_code == 400

    def test_register_invalid_username(self, client, admin):
        resp = client.post('/api/auth/agent/register', json={
            'username': 'bad user!',
            'email': 'bad@test.com',
            'password': 'password123',
        })
        assert resp.status_code == 400

    def test_register_invalid_email(self, client, admin):
        resp = client.post('/api/auth/agent/register', json={
            'username': 'validuser',
            'email': 'not-an-email',
            'password': 'password123',
        })
        assert resp.status_code == 400

    def test_register_short_password(self, client, admin):
        resp = client.post('/api/auth/agent/register', json={
            'username': 'validuser',
            'email': 'valid@test.com',
            'password': '1234567',
        })
        assert resp.status_code == 400

    def test_register_missing_fields(self, client, admin):
        resp = client.post('/api/auth/agent/register', json={
            'username': 'validuser',
        })
        assert resp.status_code == 400

    def test_registered_agent_cannot_trade(self, client, admin):
        """Newly registered agents should have trading disabled."""
        client.post('/api/auth/agent/register', json={
            'username': 'newtrader',
            'email': 'trader@test.com',
            'password': 'password123',
        })
        # Login and check
        resp = client.post('/api/auth/agent/login', json={
            'username': 'newtrader',
            'password': 'password123',
        })
        assert resp.status_code == 200
        assert resp.get_json()['user']['is_trading_enabled'] is False


class TestTokenRefresh:

    def test_refresh_token(self, client, agent):
        # Login first
        resp = client.post('/api/auth/agent/login', json={
            'username': 'testagent',
            'password': 'password123',
        })
        refresh_token = resp.get_json()['refresh_token']

        # Refresh
        resp = client.post('/api/auth/refresh', headers={
            'Authorization': f'Bearer {refresh_token}'
        })
        assert resp.status_code == 200
        assert 'access_token' in resp.get_json()


class TestChangePassword:

    def test_change_password_success(self, client, agent_token):
        resp = client.post('/api/auth/change-password',
            json={'old_password': 'password123', 'new_password': 'newpassword456'},
            headers={'Authorization': f'Bearer {agent_token}'}
        )
        assert resp.status_code == 200

    def test_change_password_wrong_old(self, client, agent_token):
        resp = client.post('/api/auth/change-password',
            json={'old_password': 'wrongold', 'new_password': 'newpassword456'},
            headers={'Authorization': f'Bearer {agent_token}'}
        )
        assert resp.status_code == 401

    def test_change_password_too_short(self, client, agent_token):
        resp = client.post('/api/auth/change-password',
            json={'old_password': 'password123', 'new_password': '1234567'},
            headers={'Authorization': f'Bearer {agent_token}'}
        )
        assert resp.status_code == 400
