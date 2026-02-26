import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import api from '../../api/client';
import { UserPlus } from 'lucide-react';

export default function Register() {
  const [form, setForm] = useState({ username: '', email: '', password: '', display_name: '' });
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setLoading(true);
    try {
      const res = await api.post('/auth/agent/register', form);
      setSuccess(res.data.message);
      setTimeout(() => navigate('/agent/login'), 2000);
    } catch (err) {
      setError(err.response?.data?.error || 'Registration failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-bg p-4">
      <div className="w-full max-w-sm">
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-primary/15 mb-4">
            <UserPlus size={28} className="text-primary" />
          </div>
          <h1 className="text-2xl font-bold">Create Account</h1>
          <p className="text-text-secondary text-sm mt-1">Register as a trading agent</p>
        </div>

        <div className="bg-bg-card rounded-xl border border-border p-6">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-xs text-text-secondary mb-1.5">Username *</label>
              <input
                type="text"
                value={form.username}
                onChange={set('username')}
                required
                minLength={3}
                maxLength={50}
                pattern="[a-zA-Z0-9_]+"
                className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
                placeholder="e.g. trader_john"
              />
            </div>
            <div>
              <label className="block text-xs text-text-secondary mb-1.5">Email *</label>
              <input
                type="email"
                value={form.email}
                onChange={set('email')}
                required
                className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
                placeholder="you@example.com"
              />
            </div>
            <div>
              <label className="block text-xs text-text-secondary mb-1.5">Display Name</label>
              <input
                type="text"
                value={form.display_name}
                onChange={set('display_name')}
                className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
                placeholder="Optional"
              />
            </div>
            <div>
              <label className="block text-xs text-text-secondary mb-1.5">Password *</label>
              <input
                type="password"
                value={form.password}
                onChange={set('password')}
                required
                minLength={8}
                className="w-full px-3 py-2 bg-bg-input rounded-lg border border-border text-text text-sm focus:outline-none focus:border-primary"
                placeholder="At least 8 characters"
              />
            </div>

            {error && (
              <p className="text-danger text-xs bg-danger/10 rounded-lg px-3 py-2">{error}</p>
            )}
            {success && (
              <p className="text-success text-xs bg-success/10 rounded-lg px-3 py-2">{success}</p>
            )}

            <button
              type="submit"
              disabled={loading}
              className="w-full py-2.5 bg-primary hover:bg-primary-dark text-white font-medium rounded-lg text-sm transition-colors disabled:opacity-50"
            >
              {loading ? 'Registering...' : 'Create Account'}
            </button>
          </form>

          <p className="text-center text-xs text-text-secondary mt-4">
            Already have an account?{' '}
            <Link to="/agent/login" className="text-primary hover:underline">Sign in</Link>
          </p>
          <p className="text-center text-xs text-text-secondary/60 mt-2">
            Trading will be enabled after admin approval.
          </p>
        </div>
      </div>
    </div>
  );
}
