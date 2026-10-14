'use client';

import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import api, { clearSession } from './api';
import type { CurrentUser, Role } from './types';

interface AuthState {
    user: CurrentUser | null;
    logout: () => Promise<void>;
    hasRole: (...roles: Role[]) => boolean;
}

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
    const router = useRouter();
    const [user, setUser] = useState<CurrentUser | null>(null);

    useEffect(() => {
        api.get<CurrentUser>('/me').then((res) => setUser(res.data)).catch(() => undefined);
    }, []);

    const logout = async () => {
        try {
            await api.post('/logout');
        } finally {
            clearSession();
            router.push('/');
        }
    };

    const hasRole = (...roles: Role[]) => (user ? roles.includes(user.role) : false);

    if (!user) {
        return (
            <div className="flex min-h-screen items-center justify-center text-sm text-gray-500">
                Loading ORIES-IS…
            </div>
        );
    }

    return <AuthContext.Provider value={{ user, logout, hasRole }}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState & { user: CurrentUser } {
    const context = useContext(AuthContext);
    if (!context || !context.user) {
        throw new Error('useAuth must be used inside AuthProvider');
    }
    return context as AuthState & { user: CurrentUser };
}
