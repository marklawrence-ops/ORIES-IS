'use client';

import { useSearchParams } from 'next/navigation';
import { Suspense, useState, type FormEvent } from 'react';
import api, { errorMessage } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { useApi, useLookups } from '@/lib/hooks';
import { OFFICE_LABELS, ROLE_LABELS } from '@/lib/format';
import type { Paginated, Role, UserAccount } from '@/lib/types';
import { Alert, Badge, Button, Field, Input, Loading, Modal, PageHeader, Pagination, Select } from '@/components/ui';

export default function UsersPage() {
    return (
        <Suspense fallback={<Loading />}>
            <UserAdmin />
        </Suspense>
    );
}

function UserAdmin() {
    const { user } = useAuth();
    const searchParams = useSearchParams();
    const [filters, setFilters] = useState({ q: '', role: '', status: searchParams.get('status') ?? '' });
    const [page, setPage] = useState(1);
    const { data, error, loading, reload } = useApi<Paginated<UserAccount>>(user.can_manage_users ? '/users' : null, { ...filters, page });
    const [editing, setEditing] = useState<UserAccount | 'new' | null>(null);
    const [message, setMessage] = useState('');

    if (!user.can_manage_users) return <Alert>Only the ORIES technical staff manage accounts.</Alert>;

    const approve = async (user: UserAccount) => {
        await api.post(`/users/${user.id}/approve`);
        setMessage(`${user.name} can now sign in.`);
        reload();
    };

    return (
        <div>
            <PageHeader
                title="User Accounts"
                description="Approve self-registered faculty and non-teaching personnel, and manage the accounts of heads, staff, directors, the VP, and OUP."
                actions={<Button onClick={() => setEditing('new')}>+ New account</Button>}
            />
            {message && <div className="mb-4"><Alert tone="green">{message}</Alert></div>}
            <div className="mb-4 grid gap-2 sm:grid-cols-3">
                <Input placeholder="Search name or email…" value={filters.q} onChange={(e) => { setFilters({ ...filters, q: e.target.value }); setPage(1); }} />
                <Select value={filters.role} onChange={(e) => { setFilters({ ...filters, role: e.target.value }); setPage(1); }}>
                    <option value="">All roles</option>
                    {Object.entries(ROLE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                </Select>
                <Select value={filters.status} onChange={(e) => { setFilters({ ...filters, status: e.target.value }); setPage(1); }}>
                    <option value="">All statuses</option>
                    <option value="pending">Awaiting approval</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </Select>
            </div>
            {error && <Alert>{error}</Alert>}
            {loading && !data ? <Loading /> : data && (
                <>
                    <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-600">
                                <tr><th className="px-4 py-3">Name</th><th className="px-4 py-3">Role</th><th className="px-4 py-3">Assignment</th><th className="px-4 py-3">Status</th><th className="px-4 py-3" /></tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {data.data.map((u) => (
                                    <tr key={u.id}>
                                        <td className="px-4 py-3"><p className="font-medium">{u.name}</p><p className="text-xs text-gray-500">{u.email}</p></td>
                                        <td className="px-4 py-3">{ROLE_LABELS[u.role]}{u.office ? ` · ${OFFICE_LABELS[u.office]}` : ''}{u.employee_type === 'non_teaching' ? ' (non-teaching)' : ''}</td>
                                        <td className="px-4 py-3 text-gray-600">{[u.campus?.name, u.unit?.code ?? u.unit?.name].filter(Boolean).join(' · ') || '—'}</td>
                                        <td className="px-4 py-3"><Badge tone={u.status === 'active' ? 'green' : u.status === 'pending' ? 'amber' : 'gray'}>{u.status}</Badge></td>
                                        <td className="space-x-2 px-4 py-3 text-right">
                                            {u.status === 'pending' && <Button onClick={() => approve(u)}>Approve</Button>}
                                            <Button variant="secondary" onClick={() => setEditing(u)}>Edit</Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination page={data.current_page} lastPage={data.last_page} onChange={setPage} />
                </>
            )}
            {editing && <UserModal user={editing === 'new' ? null : editing} onClose={() => setEditing(null)} onSaved={(msg) => { setEditing(null); setMessage(msg); reload(); }} />}
        </div>
    );
}

function UserModal({ user, onClose, onSaved }: { user: UserAccount | null; onClose: () => void; onSaved: (message: string) => void }) {
    const lookups = useLookups();
    const [form, setForm] = useState({
        name: user?.name ?? '', email: user?.email ?? '', password: '', role: (user?.role ?? 'faculty') as Role,
        employee_type: user?.employee_type ?? 'teaching', office: user?.office ?? '', position: user?.position ?? '',
        campus_id: user?.campus_id ? String(user.campus_id) : '', unit_id: user?.unit_id ? String(user.unit_id) : '', status: user?.status ?? 'active',
    });
    const [error, setError] = useState('');
    const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }));
    const units = lookups?.campuses.find((c) => String(c.id) === form.campus_id)?.units ?? [];
    const needsOffice = form.role === 'staff' || form.role === 'director';

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setError('');
        const payload = Object.fromEntries(Object.entries({ ...form, office: needsOffice ? form.office : '' }).map(([k, v]) => [k, v === '' ? null : v]));
        try {
            if (user) await api.put(`/users/${user.id}`, payload);
            else await api.post('/users', payload);
            onSaved(user ? 'Account updated.' : 'Account created.');
        } catch (err) {
            setError(errorMessage(err));
        }
    };

    return (
        <Modal open title={user ? 'Edit account' : 'New account'} onClose={onClose}>
            <form onSubmit={submit} className="space-y-3">
                {error && <Alert>{error}</Alert>}
                <Field label="Name"><Input required value={form.name} onChange={(e) => set('name', e.target.value)} /></Field>
                <Field label="Email"><Input type="email" required value={form.email} onChange={(e) => set('email', e.target.value)} /></Field>
                <Field label={user ? 'New password (leave blank to keep)' : 'Password'}><Input type="password" required={!user} value={form.password} onChange={(e) => set('password', e.target.value)} /></Field>
                <div className="grid grid-cols-2 gap-3">
                    <Field label="Role">
                        <Select value={form.role} onChange={(e) => set('role', e.target.value)}>
                            {Object.entries(ROLE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                        </Select>
                    </Field>
                    {needsOffice ? (
                        <Field label="Office" hint={form.role === 'staff' ? 'Leave blank for technical staff covering all offices' : undefined}>
                            <Select value={form.office} onChange={(e) => set('office', e.target.value)}>
                                <option value="">All offices</option>
                                {Object.entries(OFFICE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </Select>
                        </Field>
                    ) : (
                        <Field label="Personnel type">
                            <Select value={form.employee_type ?? ''} onChange={(e) => set('employee_type', e.target.value)}>
                                <option value="teaching">Teaching</option>
                                <option value="non_teaching">Non-teaching</option>
                            </Select>
                        </Field>
                    )}
                    <Field label="Campus">
                        <Select value={form.campus_id} onChange={(e) => setForm((f) => ({ ...f, campus_id: e.target.value, unit_id: '' }))}>
                            <option value="">—</option>
                            {lookups?.campuses.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </Select>
                    </Field>
                    <Field label="College / unit">
                        <Select value={form.unit_id} onChange={(e) => set('unit_id', e.target.value)} disabled={!form.campus_id}>
                            <option value="">—</option>
                            {units.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                        </Select>
                    </Field>
                    <Field label="Position"><Input value={form.position} onChange={(e) => set('position', e.target.value)} /></Field>
                    {user && (
                        <Field label="Status">
                            <Select value={form.status} onChange={(e) => set('status', e.target.value)}>
                                <option value="active">Active</option>
                                <option value="pending">Pending</option>
                                <option value="inactive">Inactive</option>
                            </Select>
                        </Field>
                    )}
                </div>
                <div className="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancel</Button>
                    <Button type="submit">Save</Button>
                </div>
            </form>
        </Modal>
    );
}
