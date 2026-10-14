'use client';

import Link from 'next/link';
import { useState, type FormEvent } from 'react';
import api, { errorMessage } from '@/lib/api';
import { useLookups } from '@/lib/hooks';
import { Alert, Button, Card, Field, Input, Select } from '@/components/ui';

export default function RegisterPage() {
    const lookups = useLookups();
    const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '', employee_type: 'teaching', position: '', campus_id: '', unit_id: '' });
    const [error, setError] = useState('');
    const [done, setDone] = useState('');
    const [saving, setSaving] = useState(false);
    const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }));
    const units = lookups?.campuses.find((c) => String(c.id) === form.campus_id)?.units ?? [];

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setError('');
        setSaving(true);
        try {
            const res = await api.post('/register', form);
            setDone(res.data.message);
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="mx-auto w-full max-w-xl p-6">
            <h1 className="text-2xl font-bold text-gray-900">Create an ORIES-IS account</h1>
            <p className="mb-6 text-sm text-gray-600">For EVSU faculty and non-teaching personnel. The ORIES office verifies every account before it can be used.</p>
            {done ? (
                <Alert tone="green">
                    {done} <Link href="/" className="font-medium underline">Back to sign in</Link>
                </Alert>
            ) : (
                <Card>
                    <form onSubmit={submit} className="space-y-4">
                        {error && <Alert>{error}</Alert>}
                        <Field label="Full name (with title)"><Input required value={form.name} onChange={(e) => set('name', e.target.value)} placeholder="e.g., Engr. Juan D. Dela Cruz" /></Field>
                        <Field label="EVSU email address"><Input type="email" required value={form.email} onChange={(e) => set('email', e.target.value)} /></Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Personnel type">
                                <Select value={form.employee_type} onChange={(e) => set('employee_type', e.target.value)}>
                                    <option value="teaching">Faculty (teaching)</option>
                                    <option value="non_teaching">Non-teaching personnel</option>
                                </Select>
                            </Field>
                            <Field label="Position / designation"><Input value={form.position} onChange={(e) => set('position', e.target.value)} placeholder="e.g., Instructor I" /></Field>
                            <Field label="Campus">
                                <Select required value={form.campus_id} onChange={(e) => setForm((f) => ({ ...f, campus_id: e.target.value, unit_id: '' }))}>
                                    <option value="">Select…</option>
                                    {lookups?.campuses.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </Select>
                            </Field>
                            <Field label="College / unit">
                                <Select required value={form.unit_id} onChange={(e) => set('unit_id', e.target.value)} disabled={!form.campus_id}>
                                    <option value="">Select…</option>
                                    {units.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                                </Select>
                            </Field>
                            <Field label="Password" hint="At least 8 characters"><Input type="password" required value={form.password} onChange={(e) => set('password', e.target.value)} /></Field>
                            <Field label="Confirm password"><Input type="password" required value={form.password_confirmation} onChange={(e) => set('password_confirmation', e.target.value)} /></Field>
                        </div>
                        <div className="flex items-center justify-between">
                            <Link href="/" className="text-sm text-gray-600 hover:underline">Back to sign in</Link>
                            <Button type="submit" disabled={saving}>{saving ? 'Submitting…' : 'Register'}</Button>
                        </div>
                    </form>
                </Card>
            )}
        </div>
    );
}
