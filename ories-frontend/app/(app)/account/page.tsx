'use client';

import { useState, type FormEvent } from 'react';
import api, { errorMessage } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { OFFICE_LABELS, ROLE_LABELS } from '@/lib/format';
import { Alert, Button, Card, Field, Input, PageHeader } from '@/components/ui';

export default function AccountPage() {
    const { user } = useAuth();
    const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' });
    const [message, setMessage] = useState<{ tone: 'green' | 'red'; text: string } | null>(null);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        try {
            await api.put('/me/password', form);
            setForm({ current_password: '', password: '', password_confirmation: '' });
            setMessage({ tone: 'green', text: 'Password updated.' });
        } catch (err) {
            setMessage({ tone: 'red', text: errorMessage(err) });
        }
    };

    return (
        <div className="mx-auto max-w-2xl space-y-6">
            <PageHeader title="My Account" />
            <Card title="Profile">
                <dl className="grid grid-cols-3 gap-y-2 text-sm">
                    <dt className="text-gray-500">Name</dt><dd className="col-span-2">{user.name}</dd>
                    <dt className="text-gray-500">Email</dt><dd className="col-span-2">{user.email}</dd>
                    <dt className="text-gray-500">Role</dt><dd className="col-span-2">{ROLE_LABELS[user.role]}{user.office ? ` · ${OFFICE_LABELS[user.office]}` : ''}</dd>
                    <dt className="text-gray-500">Position</dt><dd className="col-span-2">{user.position ?? '—'}</dd>
                    <dt className="text-gray-500">Assignment</dt><dd className="col-span-2">{[user.unit?.name, user.campus?.name].filter(Boolean).join(', ') || '—'}</dd>
                </dl>
                <p className="mt-3 text-xs text-gray-500">Contact the ORIES office to correct your profile details.</p>
            </Card>
            <Card title="Change password">
                <form onSubmit={submit} className="space-y-3">
                    {message && <Alert tone={message.tone}>{message.text}</Alert>}
                    <Field label="Current password"><Input type="password" required value={form.current_password} onChange={(e) => setForm({ ...form, current_password: e.target.value })} /></Field>
                    <Field label="New password" hint="At least 8 characters"><Input type="password" required value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></Field>
                    <Field label="Confirm new password"><Input type="password" required value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} /></Field>
                    <div className="flex justify-end"><Button type="submit">Update password</Button></div>
                </form>
            </Card>
        </div>
    );
}
