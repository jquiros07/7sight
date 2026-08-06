import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { api } from './api';

export type User = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
};

type AuthContextValue = {
    user: User | null;
    loading: boolean;
    login: (email: string, password: string) => Promise<void>;
    register: (name: string, email: string, password: string, passwordConfirmation: string) => Promise<void>;
    logout: () => Promise<void>;
    refreshUser: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get<User>('/api/user')
            .then((res) => setUser(res.data))
            .catch(() => setUser(null))
            .finally(() => setLoading(false));
    }, []);

    async function refreshUser() {
        const res = await api.get<User>('/api/user');
        setUser(res.data);
    }

    async function login(email: string, password: string) {
        await api.get('/sanctum/csrf-cookie');
        const res = await api.post<User>('/login', { email, password });
        setUser(res.data);
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
    }

    async function logout() {
        await api.post('/logout');
        setUser(null);
    }

    return <AuthContext.Provider value={{ user, loading, login, register, logout, refreshUser }}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    const ctx = useContext(AuthContext);
    if (!ctx) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return ctx;
}
