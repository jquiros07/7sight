import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { api } from './api';

export type User = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
};

type WorkspacePermissions = Record<number, string[]>;

type AuthContextValue = {
    user: User | null;
    loading: boolean;
    login: (email: string, password: string) => Promise<void>;
    register: (name: string, email: string, password: string, passwordConfirmation: string) => Promise<void>;
    logout: () => Promise<void>;
    refreshUser: () => Promise<void>;
    can: (workspaceId: number | null | undefined, permission: string) => boolean;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [permissions, setPermissions] = useState<WorkspacePermissions>({});
    const [loading, setLoading] = useState(true);

    async function loadPermissions() {
        try {
            const res = await api.get<WorkspacePermissions>('/api/permissions');
            setPermissions(res.data);
        } catch {
            setPermissions({});
        }
    }

    useEffect(() => {
        api.get<User>('/api/user')
            .then((res) => {
                setUser(res.data);
                return loadPermissions();
            })
            .catch(() => setUser(null))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    async function refreshUser() {
        const res = await api.get<User>('/api/user');
        setUser(res.data);
        await loadPermissions();
    }

    async function login(email: string, password: string) {
        await api.get('/sanctum/csrf-cookie');
        const res = await api.post<User>('/login', { email, password });
        setUser(res.data);
        await loadPermissions();
    }

    async function register(name: string, email: string, password: string, passwordConfirmation: string) {
        await api.get('/sanctum/csrf-cookie');
        const res = await api.post<User>('/register', {
            name,
            email,
            password,
            password_confirmation: passwordConfirmation,
        });
        setUser(res.data);
        await loadPermissions();
    }

    async function logout() {
        await api.post('/logout');
        setUser(null);
        setPermissions({});
    }

    function can(workspaceId: number | null | undefined, permission: string) {
        if (workspaceId == null) return false;
        return permissions[workspaceId]?.includes(permission) ?? false;
    }

    return (
        <AuthContext.Provider value={{ user, loading, login, register, logout, refreshUser, can }}>{children}</AuthContext.Provider>
    );
}

export function useAuth() {
    const ctx = useContext(AuthContext);
    if (!ctx) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return ctx;
}
