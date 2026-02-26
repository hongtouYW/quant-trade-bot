import { useState } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { Briefcase, Shield } from 'lucide-react';

export default function Login() {
  const location = useLocation();
  const role = location.pathname.startsWith('/admin') ? 'admin' : 'agent';
  const isAdmin = role === 'admin';

  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      await login(username, password, role);
      navigate(`/${role}`);
    } catch (err) {
      setError(err.response?.data?.error || 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-bg p-4">
      <div className="w-full max-w-sm">
        <div className="text-center mb-8">
          <div className={`inline-flex items-center justify-center w-14 h-14 rounded-2xl ${isAdmin ? 'bg-warning/15' : 'bg-primary/15'} mb-4`}>
            {isAdmin
              ? <Shield size={28} className="text-warning" />
              : <Briefcase size={28} className="text-primary" />
            }
          </div>
          <h1 className="text-2xl font-bold">Trading SaaS</h1>
          <p className="text-text-secondary text-sm mt-1">
            {isAdmin ? 'Admin Login' : 'Agent Login'}
          </p>
        </div>

        <div className="bg-bg-card rounded-xl border border-border p-6">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-xs text-text-secondary mb-1.5">Username</label>
              <input
                type="text"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                required
                className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
              />
            </div>
            <div>
              <label className="block text-xs text-text-secondary mb-1.5">Password</label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
              />
            </div>

            {error && (
              <p className="text-danger text-xs bg-danger/10 rounded-lg px-3 py-2">{error}</p>
            )}

            <button
              type="submit"
              disabled={loading}
              className={`w-full py-2.5 ${isAdmin ? 'bg-warning hover:bg-warning/90' : 'bg-primary hover:bg-primary-dark'} text-white font-medium rounded-lg text-sm transition-colors disabled:opacity-50`}
            >
              {loading ? 'Signing in...' : `Sign In as ${isAdmin ? 'Admin' : 'Agent'}`}
            </button>
          </form>

          {!isAdmin && (
            <p className="text-center text-xs text-text-secondary mt-4">
              Don't have an account?{' '}
              <Link to="/agent/register" className="text-primary hover:underline">Register</Link>
            </p>
          )}
        </div>
      </div>
    </div>
  );
}
