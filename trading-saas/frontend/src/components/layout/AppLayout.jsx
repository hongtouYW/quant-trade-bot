import { Outlet, Navigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import Sidebar from './Sidebar';

export default function AppLayout({ requiredRole }) {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-bg">
        <div className="animate-spin w-8 h-8 border-2 border-primary border-t-transparent rounded-full" />
      </div>
    );
  }

  if (!user) return <Navigate to={`/${requiredRole}/login`} replace />;
  if (requiredRole && user.role !== requiredRole) {
    return <Navigate to={`/${user.role}`} replace />;
  }

  return (
    <div className="min-h-screen bg-bg">
      <Sidebar />
      <main className="pt-14 pb-20 lg:pt-0 lg:pb-0 lg:ml-56 p-4 lg:p-6">
        <Outlet />
      </main>
    </div>
  );
}
