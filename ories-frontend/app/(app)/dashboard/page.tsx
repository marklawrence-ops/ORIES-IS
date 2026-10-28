'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useAuth } from '@/lib/auth';
import { useApi } from '@/lib/hooks';
import { formatDate, OFFICE_LABELS, roleLabel } from '@/lib/format';
import type { Office, Project, Stage, VerificationStatus } from '@/lib/types';
import { Alert, ButtonLink, Card, Loading, Modal, OfficeBadge, PageHeader, StageBadge, VerificationBadge } from '@/components/ui';

interface Stat {
    key: string;
    label: string;
    value: number;
    filters: Record<string, string>;
}

interface Dashboard {
    role: string;
    scope: string;
    stats: Stat[];
    people: { key: string; label: string; value: number; office: Office }[];
    by_office: { office: Office; total: number }[];
    by_campus: { campus_id: number; campus: string; total: number }[];
    trend: { year: number; proposals: number; completed: number }[];
    tasks: { label: string; value: number; link: string; tone?: 'red' }[];
    recent: (Pick<Project, 'id' | 'title' | 'office' | 'updated_at'> & { stage: Stage; verification_status: VerificationStatus; lead?: { name: string } })[];
}

interface PeopleDrilldown {
    people: { id: number; name: string; position: string | null; unit?: { code: string | null; name: string } | null; campus?: { name: string } | null; ongoing: number; total: number }[];
}

const recordsLink = (filters: Record<string, string | number>) =>
    `/projects?${new URLSearchParams({ scope: 'responsibility', ...Object.fromEntries(Object.entries(filters).map(([k, v]) => [k, String(v)])) })}`;

export default function DashboardPage() {
    const { user, hasRole } = useAuth();
    const { data, error, loading } = useApi<Dashboard>('/dashboard');
    const [drill, setDrill] = useState<{ office: Office; label: string } | null>(null);

    if (loading) return <Loading />;
    if (error || !data) return <Alert>{error ?? 'The dashboard could not be loaded.'}</Alert>;

    const maxOffice = Math.max(1, ...data.by_office.map((o) => o.total));
    const maxCampus = Math.max(1, ...data.by_campus.map((c) => c.total));
    const maxTrend = Math.max(1, ...data.trend.flatMap((t) => [t.proposals, t.completed]));

    return (
        <div className="space-y-6">
            <PageHeader
                title={`Welcome, ${user.name.split(' ').slice(0, 2).join(' ')}`}
                description={<>{roleLabel(user.role, user.office)} · Showing: <strong>{data.scope}</strong>. Click any figure to see the records behind it.</>}
                actions={hasRole('faculty', 'head') ? <ButtonLink href="/projects/new">+ Submit a proposal</ButtonLink> : undefined}
            />

            {data.tasks.some((t) => t.value > 0) && (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {data.tasks.filter((t) => t.value > 0).map((task) => (
                        <Link key={task.label} href={task.link} className={`rounded-lg border px-4 py-3 ${task.tone === 'red' ? 'border-red-200 bg-red-50 hover:border-red-400' : 'border-amber-200 bg-amber-50 hover:border-amber-400'}`}>
                            <p className={`text-2xl font-bold ${task.tone === 'red' ? 'text-red-700' : 'text-amber-800'}`}>{task.value}</p>
                            <p className={`text-sm ${task.tone === 'red' ? 'text-red-900' : 'text-amber-900'}`}>{task.label} →</p>
                        </Link>
                    ))}
                </div>
            )}

            <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                {data.stats.map((stat) => (
                    <Link key={stat.key} href={recordsLink(stat.filters)} className="group rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-brand-500">
                        <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{stat.label}</p>
                        <p className="mt-1 text-3xl font-bold text-gray-900 group-hover:text-brand-700">{stat.value}</p>
                    </Link>
                ))}
            </div>

            {data.people.length > 0 && (
                <div className="grid gap-3 sm:grid-cols-3">
                    {data.people.map((p) => (
                        <button key={p.key} onClick={() => setDrill({ office: p.office, label: p.label })} className="rounded-lg border border-gray-200 bg-white p-4 text-left shadow-sm hover:border-brand-500">
                            <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{p.label}</p>
                            <p className="mt-1 text-3xl font-bold text-brand-700">{p.value}</p>
                            <p className="text-xs text-gray-500">personnel with verified ongoing {OFFICE_LABELS[p.office].toLowerCase()} work · view list</p>
                        </button>
                    ))}
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-3">
                <Card title="Records by office">
                    <div className="space-y-3">
                        {data.by_office.map((o) => (
                            <Link key={o.office} href={recordsLink({ office: o.office, verification_status: 'verified' })} className="block group">
                                <div className="flex justify-between text-sm"><span className="group-hover:underline">{OFFICE_LABELS[o.office]}</span><span className="font-semibold">{o.total}</span></div>
                                <div className="mt-1 h-2 rounded bg-gray-100"><div className="h-2 rounded bg-brand-600" style={{ width: `${(o.total / maxOffice) * 100}%` }} /></div>
                            </Link>
                        ))}
                    </div>
                </Card>
                <Card title="Records by campus">
                    {data.by_campus.length === 0 ? <p className="text-sm text-gray-500">No verified records yet.</p> : (
                        <div className="space-y-3">
                            {data.by_campus.map((c) => (
                                <Link key={c.campus_id} href={recordsLink({ campus_id: c.campus_id, verification_status: 'verified' })} className="block group">
                                    <div className="flex justify-between text-sm"><span className="group-hover:underline">{c.campus}</span><span className="font-semibold">{c.total}</span></div>
                                    <div className="mt-1 h-2 rounded bg-gray-100"><div className="h-2 rounded bg-gold-500" style={{ width: `${(c.total / maxCampus) * 100}%` }} /></div>
                                </Link>
                            ))}
                        </div>
                    )}
                </Card>
                <Card title="Five-year trend">
                    <div className="flex h-40 items-end gap-3">
                        {data.trend.map((t) => (
                            <Link key={t.year} href={recordsLink({ year: t.year, verification_status: 'verified' })} className="flex flex-1 flex-col items-center gap-1">
                                <div className="flex h-32 w-full items-end justify-center gap-1">
                                    <div title={`${t.proposals} proposals`} className="w-1/2 rounded-t bg-brand-200" style={{ height: `${(t.proposals / maxTrend) * 100}%` }} />
                                    <div title={`${t.completed} completed`} className="w-1/2 rounded-t bg-brand-700" style={{ height: `${(t.completed / maxTrend) * 100}%` }} />
                                </div>
                                <span className="text-xs text-gray-600">{t.year}</span>
                            </Link>
                        ))}
                    </div>
                    <p className="mt-2 flex gap-4 text-xs text-gray-500">
                        <span><span className="mr-1 inline-block h-2 w-2 bg-brand-200" />Proposals</span>
                        <span><span className="mr-1 inline-block h-2 w-2 bg-brand-700" />Completed</span>
                    </p>
                </Card>
            </div>

            <Card title="Recently updated records" actions={<Link href="/projects?scope=responsibility" className="text-sm text-brand-700 hover:underline">View all</Link>}>
                {data.recent.length === 0 ? <p className="text-sm text-gray-500">No records yet.</p> : (
                    <ul className="divide-y divide-gray-100">
                        {data.recent.map((r) => (
                            <li key={r.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                <Link href={`/projects/${r.id}`} className="font-medium text-gray-900 hover:text-brand-700">{r.title}</Link>
                                <span className="flex items-center gap-2 text-xs text-gray-500">
                                    <OfficeBadge office={r.office} />
                                    {r.verification_status === 'verified' ? <StageBadge stage={r.stage} /> : <VerificationBadge status={r.verification_status} />}
                                    {formatDate(r.updated_at)}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            {drill && <PeopleModal office={drill.office} label={drill.label} onClose={() => setDrill(null)} />}
        </div>
    );
}

function PeopleModal({ office, label, onClose }: { office: Office; label: string; onClose: () => void }) {
    const { data, loading } = useApi<PeopleDrilldown>('/dashboard/people', { office });

    return (
        <Modal open title={label} onClose={onClose}>
            {loading ? <Loading /> : (
                <ul className="divide-y">
                    {data?.people.length === 0 && <li className="py-4 text-sm text-gray-500">No active personnel found.</li>}
                    {data?.people.map((p) => (
                        <li key={p.id} className="py-2">
                            <Link href={`/projects?${new URLSearchParams({ member_id: String(p.id), office })}`} className="font-medium text-brand-700 hover:underline">{p.name}</Link>
                            <p className="text-xs text-gray-500">{[p.position, p.unit?.code ?? p.unit?.name, p.campus?.name].filter(Boolean).join(' · ')}</p>
                            <p className="text-xs text-gray-600">{p.ongoing} ongoing · {p.total} verified record(s)</p>
                        </li>
                    ))}
                </ul>
            )}
        </Modal>
    );
}
