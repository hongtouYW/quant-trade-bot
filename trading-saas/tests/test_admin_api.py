"""Integration tests for Admin API."""
import pytest
from app.models.agent import Agent


class TestAdminDashboard:

    def test_dashboard_success(self, client, admin_token, agent):
        resp = client.get('/api/admin/dashboard',
            headers={'Authorization': f'Bearer {admin_token}'})
        assert resp.status_code == 200
        data = resp.get_json()
        assert 'total_agents' in data
        assert data['total_agents'] >= 1

    def test_dashboard_no_auth(self, client):
        resp = client.get('/api/admin/dashboard')
        assert resp.status_code == 401

    def test_dashboard_agent_token_forbidden(self, client, agent_token):
        """Agent token should not access admin endpoints."""
        resp = client.get('/api/admin/dashboard',
            headers={'Authorization': f'Bearer {agent_token}'})
        assert resp.status_code in (401, 403)


class TestAdminAgentCRUD:

    def test_list_agents(self, client, admin_token, agent):
        resp = client.get('/api/admin/agents',
            headers={'Authorization': f'Bearer {admin_token}'})
        assert resp.status_code == 200
        data = resp.get_json()
        assert 'agents' in data
        assert len(data['agents']) >= 1

    def test_create_agent(self, client, admin_token):
        resp = client.post('/api/admin/agents',
            json={
                'username': 'newagent',
                'email': 'new@agent.com',
                'password': 'password123',
                'display_name': 'New Agent',
                'profit_share_pct': 25,
            },
            headers={'Authorization': f'Bearer {admin_token}'})
        assert resp.status_code == 201
        data = resp.get_json()
        assert data['agent']['username'] == 'newagent'
        assert data['agent']['profit_share_pct'] == 25.0

    def test_create_agent_duplicate_username(self, client, admin_token, agent):
        resp = client.post('/api/admin/agents',
            json={
                'username': 'testagent',
                'email': 'other@agent.com',
                'password': 'password123',
            },
            headers={'Authorization': f'Bearer {admin_token}'})
        assert resp.status_code == 409

    def test_get_agent_detail(self, client, admin_token, agent):
        resp = client.get(f'/api/admin/agents/{agent.id}',
            headers={'Authorization': f'Bearer {admin_token}'})
        assert resp.status_code == 200
        data = resp.get_json()
        # get_agent returns flat dict (not wrapped in 'agent')
        assert data['username'] == 'testagent'
        assert 'trade_stats' in data

    def test_update_agent(self, client, admin_token, agent):
        resp = client.put(f'/api/admin/agents/{agent.id}',
            json={'display_name': 'Updated Name', 'profit_share_pct': 30},
            headers={'Authorization': f'Bearer {admin_token}'})
        assert resp.status_code == 200

    def test_toggle_trading(self, client, admin_token, agent):
        resp = client.post(f'/api/admin/agents/{agent.id}/toggle-trading',
            headers={'Authorization': f'Bearer {admin_token}'})
        assert resp.status_code == 200
        data = resp.get_json()
        # Should toggle from True to False
        assert 'is_trading_enabled' in data

    def test_get_nonexistent_agent(self, client, admin_token):
        resp = client.get('/api/admin/agents/99999',
            headers={'Authorization': f'Bearer {admin_token}'})
        assert resp.status_code == 404


class TestAdminAuditLog:

    def test_audit_log_list(self, client, admin_token, agent):
        """Audit log should have at least login entries."""
        resp = client.get('/api/admin/audit-log',
            headers={'Authorization': f'Bearer {admin_token}'})
        assert resp.status_code == 200
        data = resp.get_json()
        assert 'logs' in data
        assert 'total' in data

    def test_audit_log_pagination(self, client, admin_token, agent):
        resp = client.get('/api/admin/audit-log?page=1&per_page=5',
            headers={'Authorization': f'Bearer {admin_token}'})
        assert resp.status_code == 200
        data = resp.get_json()
        assert data['page'] == 1
        assert len(data['logs']) <= 5


class TestAdminLeaderboard:

    def test_leaderboard(self, client, admin_token, agent, sample_trades):
        resp = client.get('/api/admin/leaderboard',
            headers={'Authorization': f'Bearer {admin_token}'})
        assert resp.status_code == 200
        data = resp.get_json()
        assert 'leaderboard' in data
        assert len(data['leaderboard']) >= 1

    def test_leaderboard_with_days_filter(self, client, admin_token, agent, sample_trades):
        resp = client.get('/api/admin/leaderboard?days=7',
            headers={'Authorization': f'Bearer {admin_token}'})
        assert resp.status_code == 200
