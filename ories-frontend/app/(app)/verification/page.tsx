'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useAuth } from '@/lib/auth';
import { useApi } from '@/lib/hooks';
import { formatDate, OFFICE_LABELS } from '@/lib/format';
import type { Paginated, ProjectUpdate } from '@/lib/types';
import { Alert, Badge, EmptyState, Loading, OfficeBadge, PageHeader, Pagination } from '@/components/ui';

const TABS = [
    { key: 'pending', label: 'Awaiting verification' },
    { key: 'verified', label: 'Verified' },
    { key: 'returned', label: 'Returned' },
] as const;

export default function VerificationQueuePage() {
    const { user, hasRole } = useAuth();
    const [status, setStatus] = useState<(typeof TABS)[number]['key']>('pending');
    const [page, setPage] = useState(1);
    const { data, error, loading } = useApi<Paginated<ProjectUpdate>>(hasRole('staff', 'director') ? '/verification' : null, { status, page });

    if (!hasRole('staff', 'director')) return <Alert>Only ORIES staff and directors verify submissions.</Alert>;

    return (
        <div>
            <PageHeader
                title="Verification Queue"
                description={`Every proposal, stage change, and outcome is checked against its Means of Verification before it counts in reports. Showing: ${user.office ? `${OFFICE_LABELS[user.office]} office` : 'all offices'}.`}
            />
            <div className="mb-4 flex gap-1 border-b border-gray-200">
                {TABS.map((tab) => (
                    <button
                        key={tab.key}
                        onClick={() => { setStatus(tab.key); setPage(1); }}
                        className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium ${status === tab.key ? 'border-brand-700 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-800'}`}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {error && <Alert>{error}</Alert>}
            {loading && !data ? <Loading /> : data && data.data.length === 0 ? (
                <EmptyState>{status === 'pending' ? 'Nothing is waiting for verification.' : 'No submissions in this list yet.'}</EmptyState>
            ) : data && (
                <>
                    <ul className="space-y-3">
                        {data.data.map((u) => (
                            <li key={u.id}>
                                <Link href={`/projects/${u.project_id}`} className="block rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-brand-500">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p className="font-medium text-gray-900">{u.project?.title}</p>
                                            <p className="text-sm text-gray-600">{u.summary} · {u.submitter?.name}</p>
                                            <p className="text-xs text-gray-500">{u.project?.campus?.name}{u.project?.unit ? ` · ${u.project.unit.code ?? u.project.unit.name}` : ''} · {u.attachments_count} file(s)</p>
                                        </div>
                                        <div className="flex flex-col items-end gap-1 text-xs text-gray-500">
                                            {u.project && <OfficeBadge office={u.project.office} />}
                                            {!!u.duplicate_matches?.length && <Badge tone="amber">Possible duplicate title</Badge>}
                                            <span>{status === 'pending' ? `Submitted ${formatDate(u.created_at, true)}` : `Reviewed ${formatDate(u.reviewed_at, true)} by ${u.reviewer?.name ?? ''}`}</span>
                                        </div>
                                    </div>
                                    {u.review_remarks && <p className="mt-2 text-sm text-gray-600">Remarks: {u.review_remarks}</p>}
                                </Link>
                            </li>
                        ))}
                    </ul>
                    <Pagination page={data.current_page} lastPage={data.last_page} onChange={setPage} />
                </>
            )}
        </div>
    );
}
