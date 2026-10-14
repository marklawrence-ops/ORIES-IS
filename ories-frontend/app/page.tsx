'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState, type FormEvent } from 'react';
import api, { errorMessage, saveSession } from '@/lib/api';
import { Alert, Button, Field, Input } from '@/components/ui';

export default function LoginPage() {
    const router = useRouter();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const handleLogin = async (e: FormEvent) => {
        e.preventDefault();
        setError('');
        setLoading(true);

        try {
            const response = await api.post('/login', { email, password });
            saveSession(response.data.access_token, response.data.user.role);
            router.push('/dashboard');
        } catch (err) {
            setError(errorMessage(err, 'Authentication failed. Please check your credentials.'));
            setLoading(false);
        }
    };

    return (
        <div className="flex min-h-screen flex-col lg:flex-row">
            <div className="flex flex-col justify-between bg-brand-700 px-8 py-10 text-white lg:w-1/2 lg:px-16">
                <div>
                    <p className="text-sm font-semibold uppercase tracking-widest text-gold-400">Eastern Visayas State University</p>
                    <h1 className="mt-4 text-3xl font-bold leading-tight lg:text-4xl">Office of Research, Innovation, and Extension Services Information System</h1>
                    <p className="mt-4 max-w-md text-brand-100">
                        Submit, track, and verify research, innovation, and extension records from proposal to publication,
                        commercialization, policy, and technology transfer — in one place for all EVSU campuses.
                    </p>
                </div>
                <p className="mt-10 text-xs text-brand-200">ORIES-IS · Tacloban · Burauen · Carigara · Dulag · Ormoc · Tanauan</p>
            </div>

            <div className="flex flex-1 items-center justify-center p-6">
                <form onSubmit={handleLogin} className="w-full max-w-sm space-y-5">
                    <div>
                        <h2 className="text-2xl font-bold text-gray-900">Sign in</h2>
                        <p className="text-sm text-gray-600">Use your EVSU account.</p>
                    </div>
                    {error && <Alert>{error}</Alert>}
                    <Field label="Email address">
                        <Input type="email" required autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="name@evsu.edu.ph" />
                    </Field>
                    <Field label="Password">
                        <Input type="password" required autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} />
                    </Field>
                    <Button type="submit" disabled={loading} className="w-full">{loading ? 'Signing in…' : 'Sign in'}</Button>
                    <p className="text-center text-sm text-gray-600">
                        Faculty or non-teaching personnel without an account?{' '}
                        <Link href="/register" className="font-medium text-brand-700 hover:underline">Register</Link>
                    </p>
                    <p className="text-center text-xs text-gray-500">
                        <Link href="/verify" className="hover:underline">Verify an ORIES certificate</Link>
                    </p>
                </form>
            </div>
        </div>
    );
}
