'use client';

import type { ReactNode } from 'react';
import AppShell from '@/components/AppShell';
import { AuthProvider } from '@/lib/auth';

export default function AuthenticatedLayout({ children }: { children: ReactNode }) {
    return (
        <AuthProvider>
            <AppShell>{children}</AppShell>
        </AuthProvider>
    );
}
