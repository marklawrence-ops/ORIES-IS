'use client';

import Link from 'next/link';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { Suspense, useState } from 'react';
import { useAuth } from '@/lib/auth';
import { useApi, useLookups } from '@/lib/hooks';
import { formatDate, INDEXING_LABELS, OFFICE_LABELS, OUTCOME_LABELS, STAGE_LABELS } from '@/lib/format';
import type { Paginated, Project } from '@/lib/types';
import { Alert, Button, ButtonLink, Card, DeadlineBadge, EmptyState, Input, Loading, OfficeBadge, PageHeader, Pagination, Select, StageBadge, VerificationBadge } from '@/components/ui';

const FILTER_KEYS = ['q', 'scope', 'activity', 'deadline', 'office', 'stage', 'verification_status', 'campus_id', 'unit_id', 'outcome', 'indexing', 'year', 'member_id', 'page'] as const;

// Lists opened from the faculty dashboard's task tiles
const ACTIVITY_VIEWS: Record<string, { title: string; description: string }> = {
    awaiting: { title: 'Awaiting verification', description: 'Your records with a submission the ORIES office has not checked yet.' },
    returned: { title: 'Returned for correction', description: 'Your records whose latest submission was returned. Open each one to read the remarks, then correct and resubmit.' },
};

// Lists opened from the deadline tiles
const DEADLINE_VIEWS: Record<string, { title: string; description: string; empty: string }> = {
    overdue: {
        title: 'Past their end date',
        description: 'Verified ongoing records whose end date has passed. The lead should submit a completion or termination update with its Means of Verification.',
        empty: 'No ongoing records are past their end date.',
    },
    ending_soon: {
        title: 'Ending soon',
        description: 'Verified ongoing records scheduled to end within the next few weeks.',
        empty: 'No ongoing records are ending soon.',
    },
};

export default function ProjectsPage() {
    return (
        <Suspense fallback={<Loading />}>
            <ProjectSearch />
        </Suspense>
    );
}

// Remounted (via key) whenever the URL query changes, so it always starts from the current search
function SearchBox({ initial, onSearch }: { initial: string; onSearch: (q: string) => void }) {
    const [query, setQuery] = useState(initial);

    return (
        <form onSubmit={(e) => { e.preventDefault(); onSearch(query.trim()); }} className="flex gap-2">
            <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search by title, keyword, member, or partner…" />
            <Button type="submit">Search</Button>
        </form>
    );
}

function ProjectSearch() {
    const { hasRole } = useAuth();
    const lookups = useLookups();
    const router = useRouter();
    const pathname = usePathname();
    const searchParams = useSearchParams();

    const params = Object.fromEntries(FILTER_KEYS.map((k) => [k, searchParams.get(k) ?? '']).filter(([, v]) => v !== ''));
    const { data, error, loading } = useApi<Paginated<Project>>('/projects', params);

    const setParam = (changes: Record<string, string>) => {
        const next = new URLSearchParams(searchParams.toString());
        Object.entries(changes).forEach(([k, v]) => (v ? next.set(k, v) : next.delete(k)));
        if (!('page' in changes)) next.delete('page');
        router.push(`${pathname}?${next.toString()}`);
    };

    const isMine = params.scope === 'mine';
    const activity = params.activity ? ACTIVITY_VIEWS[params.activity] : params.deadline ? DEADLINE_VIEWS[params.deadline] : undefined;
    const units = lookups?.campuses.find((c) => String(c.id) === params.campus_id)?.units ?? [];
    const activeFilters = Object.keys(params).filter((k) => !['q', 'scope', 'page'].includes(k));

    return (
        <div>
            <PageHeader
                title={activity?.title ?? (isMine ? 'My Records' : 'Research, Innovation, and Extension Records')}
                description={activity?.description ?? (isMine ? 'Records you lead or are a member of, including those awaiting verification.' : 'Search verified records across EVSU. Unverified records appear only to the people involved and their reviewers.')}
                actions={hasRole('faculty', 'head') ? <ButtonLink href="/projects/new">+ Submit a proposal</ButtonLink> : undefined}
            />

            <Card className="mb-4">
                <SearchBox key={params.q ?? ''} initial={params.q ?? ''} onSearch={(q) => setParam({ q })} />
                <div className="mt-3 grid grid-cols-2 gap-2 md:grid-cols-4 lg:grid-cols-8">
                    <Select value={params.scope ?? ''} onChange={(e) => setParam({ scope: e.target.value })}>
                        <option value="">All visible records</option>
                        {hasRole('faculty', 'head') && <option value="mine">My records</option>}
                        {!hasRole('faculty') && <option value="responsibility">My area of responsibility</option>}
                    </Select>
                    <Select value={params.office ?? ''} onChange={(e) => setParam({ office: e.target.value })}>
                        <option value="">All offices</option>
                        {lookups?.offices.map((o) => <option key={o} value={o}>{OFFICE_LABELS[o]}</option>)}
                    </Select>
                    <Select value={params.stage ?? ''} onChange={(e) => setParam({ stage: e.target.value })}>
                        <option value="">All stages</option>
                        {lookups?.stages.map((s) => <option key={s} value={s}>{STAGE_LABELS[s]}</option>)}
                    </Select>
                    <Select value={params.outcome ?? ''} onChange={(e) => setParam({ outcome: e.target.value })}>
                        <option value="">Any outcome</option>
                        {lookups?.outcome_types.map((o) => <option key={o} value={o}>{OUTCOME_LABELS[o]}</option>)}
                    </Select>
                    <Select value={params.indexing ?? ''} onChange={(e) => setParam({ indexing: e.target.value })}>
                        <option value="">Any indexing</option>
                        {lookups?.indexing.map((i) => <option key={i} value={i}>{INDEXING_LABELS[i]}</option>)}
                    </Select>
                    <Select value={params.campus_id ?? ''} onChange={(e) => setParam({ campus_id: e.target.value, unit_id: '' })}>
                        <option value="">All campuses</option>
                        {lookups?.campuses.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </Select>
                    <Select value={params.unit_id ?? ''} onChange={(e) => setParam({ unit_id: e.target.value })} disabled={!params.campus_id}>
                        <option value="">All colleges/units</option>
                        {units.map((u) => <option key={u.id} value={u.id}>{u.code ?? u.name}</option>)}
                    </Select>
                    <Input type="number" placeholder="Year" value={params.year ?? ''} onChange={(e) => setParam({ year: e.target.value })} />
                </div>
                {activeFilters.length > 0 && (
                    <button className="mt-2 text-xs text-brand-700 hover:underline" onClick={() => router.push(`${pathname}${params.scope ? `?scope=${params.scope}` : ''}`)}>
                        {activity ? `Show all ${isMine ? 'my ' : ''}records` : 'Clear filters'}
                    </button>
                )}
            </Card>

            {error && <Alert>{error}</Alert>}
            {loading && !data ? <Loading /> : data && data.data.length === 0 ? (
                <EmptyState>
                    {params.activity === 'awaiting' ? 'None of your records are waiting for verification.'
                        : params.activity === 'returned' ? 'Nothing to correct. None of your records have been returned.'
                            : params.deadline ? DEADLINE_VIEWS[params.deadline]?.empty ?? 'No records match these filters.'
                                : 'No records match these filters.'}
                </EmptyState>
            ) : data && (
                <>
                    <p className="mb-2 text-sm text-gray-600">{data.total} record(s)</p>
                    <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-600">
                                <tr>
                                    <th className="px-4 py-3">Title</th>
                                    <th className="hidden px-4 py-3 md:table-cell">Lead</th>
                                    <th className="px-4 py-3">Office</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="hidden px-4 py-3 lg:table-cell">Campus / Unit</th>
                                    <th className="hidden px-4 py-3 lg:table-cell">Updated</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {data.data.map((p) => (
                                    <tr key={p.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <Link href={`/projects/${p.id}`} className="font-medium text-gray-900 hover:text-brand-700">{p.title}</Link>
                                            {!!p.outcomes_count && <span className="ml-2 text-xs text-gray-500">· {p.outcomes_count} outcome(s)</span>}
                                        </td>
                                        <td className="hidden px-4 py-3 text-gray-700 md:table-cell">{p.lead?.name}</td>
                                        <td className="px-4 py-3"><OfficeBadge office={p.office} /></td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {p.verification_status === 'verified' ? <StageBadge stage={p.stage} /> : <VerificationBadge status={p.verification_status} />}
                                                <DeadlineBadge deadline={p.deadline} endDate={p.end_date} />
                                            </div>
                                        </td>
                                        <td className="hidden px-4 py-3 text-gray-600 lg:table-cell">{p.campus?.name}{p.unit ? ` · ${p.unit.code ?? p.unit.name}` : ''}</td>
                                        <td className="hidden px-4 py-3 text-gray-500 lg:table-cell">{formatDate(p.updated_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination page={data.current_page} lastPage={data.last_page} onChange={(page) => setParam({ page: String(page) })} />
                </>
            )}
        </div>
    );
}
