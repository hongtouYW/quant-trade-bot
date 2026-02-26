import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import AppLayout from './components/layout/AppLayout';
import Login from './pages/auth/Login';
import Register from './pages/auth/Register';

// Admin pages
import AdminDashboard from './pages/admin/Dashboard';
import Agents from './pages/admin/Agents';
import Bots from './pages/admin/Bots';
import Revenue from './pages/admin/Revenue';
import AuditLog from './pages/admin/AuditLog';

// Agent pages
import AgentDashboard from './pages/agent/Dashboard';
import Positions from './pages/agent/Positions';
import History from './pages/agent/History';
import Stats from './pages/agent/Stats';
import BotControl from './pages/agent/BotControl';
import Billing from './pages/agent/Billing';
import Settings from './pages/agent/Settings';

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          {/* Separate login pages */}
          <Route path="/admin/login" element={<Login />} />
          <Route path="/agent/login" element={<Login />} />
          <Route path="/agent/register" element={<Register />} />

          {/* Admin routes */}
          <Route element={<AppLayout requiredRole="admin" />}>
            <Route path="/admin" element={<AdminDashboard />} />
            <Route path="/admin/agents" element={<Agents />} />
            <Route path="/admin/bots" element={<Bots />} />
            <Route path="/admin/billing" element={<Revenue />} />
            <Route path="/admin/audit" element={<AuditLog />} />
          </Route>

          {/* Agent routes */}
          <Route element={<AppLayout requiredRole="agent" />}>
            <Route path="/agent" element={<AgentDashboard />} />
            <Route path="/agent/positions" element={<Positions />} />
            <Route path="/agent/history" element={<History />} />
            <Route path="/agent/stats" element={<Stats />} />
            <Route path="/agent/bot" element={<BotControl />} />
            <Route path="/agent/billing" element={<Billing />} />
            <Route path="/agent/settings" element={<Settings />} />
          </Route>

          {/* Legacy /login redirect */}
          <Route path="/login" element={<Navigate to="/agent/login" replace />} />
          <Route path="*" element={<Navigate to="/agent/login" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
