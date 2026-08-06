import { type ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from './auth';

export function RequireAuth({ children, requireVerified = true }: { children: ReactNode; requireVerified?: boolean }) {
    const { user, loading } = useAuth();

    if (loading) {
        return null;
    }

    if (!user) {
        return <Navigate to="/login" replace />;
    }

    if (requireVerified && !user.email_verified_at) {
        return <Navigate to="/verify-email" replace />;
    }

    return <>{children}</>;
}
