'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useApi } from '@/lib/hooks';
import { OFFICE_LABELS, ROLE_LABELS } from '@/lib/format';
import type { OrgNode, Project, Role, Unit, Campus } from '@/lib/types';
import { Alert, Loading, Modal, OfficeBadge, PageHeader, StageBadge, VerificationBadge } from '@/components/ui';

interface PersonProfile {
    person: { id: number; name: string; position: string | null; role: Role; office: string | null; unit: Unit | null; campus: Campus | null };
    projects: Project[];
}

const TONE: Record<OrgNode['type'], string> = {
    executive: 'border-brand-700 bg-brand-700 text-white',
    office: 'border-brand-500 bg-brand-50 text-brand-900',
    group: 'border-gray-300 bg-white text-gray-800',
    campus: 'border-gold-500 bg-amber-50 text-amber-900',
    unit: 'border-gray-200 bg-white text-gray-800',
};

export default function OrgChartPage() {
    const { data, error, loading } = useApi<OrgNode>('/org-chart');
    const [personId, setPersonId] = useState<number | null>(null);

    if (loading) return <Loading />;
    if (error || !data) return <Alert>{error ?? 'The organizational chart could not be loaded.'}</Alert>;

    return (
        <div>
            <PageHeader
                title="ORIES Organizational Chart"
                description="Built from live accounts. Click a box to expand or collapse it, a person to see their records, or a record count to open the matching list."
            />
            <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white p-6">
                <OrgBranch node={data} depth={0} onPerson={setPersonId} />
            </div>
            {personId && <PersonModal id={personId} onClose={() => setPersonId(null)} />}
        </div>
    );
}

function OrgBranch({ node, depth, onPerson }: { node: OrgNode; depth: number; onPerson: (id: number) => void }) {
    const [open, setOpen] = useState(depth < 2);
    const hasChildren = node.children.length > 0;
    const recordsHref = node.office ? `/projects?office=${node.office}` : node.unit_id ? `/projects?unit_id=${node.unit_id}` : node.campus_id ? `/projects?campus_id=${node.campus_id}` : null;

    return (
        <div className={depth > 0 ? 'ml-6 border-l border-gray-200 pl-4' : ''}>
            <div className={`my-2 inline-block min-w-64 max-w-xl rounded-lg border-2 px-4 py-3 ${TONE[node.type]}`}>
                <button className="flex w-full items-start justify-between gap-3 text-left" onClick={() => hasChildren && setOpen(!open)}>
                    <span className="font-semibold">{node.label}</span>
                    {hasChildren && <span className="text-xs opacity-70">{open ? '▾' : `▸ ${node.children.length}`}</span>}
                </button>
                {node.people.length > 0 && (
                    <ul className="mt-2 space-y-0.5 text-sm">
                        {node.people.map((p) => (
                            <li key={p.id}>
                                <button onClick={() => onPerson(p.id)} className="text-left underline-offset-2 hover:underline">
                                    {p.name}
                                </button>
                                <span className="ml-1 text-xs opacity-75">{p.position ?? ROLE_LABELS[p.role]}</span>
                            </li>
                        ))}
                    </ul>
                )}
                {(node.records !== undefined || node.faculty_count !== undefined) && (
                    <p className="mt-2 flex gap-3 text-xs opacity-80">
                        {node.records !== undefined && recordsHref && <Link href={recordsHref} className="hover:underline">{node.records} verified record(s)</Link>}
                        {node.faculty_count !== undefined && <span>{node.faculty_count} faculty/personnel</span>}
                    </p>
                )}
            </div>
            {open && node.children.map((child) => <OrgBranch key={child.id} node={child} depth={depth + 1} onPerson={onPerson} />)}
        </div>
    );
}

function PersonModal({ id, onClose }: { id: number; onClose: () => void }) {
    const { data, loading } = useApi<PersonProfile>(`/org-chart/people/${id}`);

    return (
        <Modal open title={data?.person.name ?? 'Profile'} onClose={onClose}>
            {loading || !data ? <Loading /> : (
                <div className="space-y-4">
                    <p className="text-sm text-gray-700">
                        {data.person.position ?? ROLE_LABELS[data.person.role]}
                        {data.person.office && ` · ${OFFICE_LABELS[data.person.office as keyof typeof OFFICE_LABELS]}`}
                        {data.person.unit && <span className="block text-gray-500">{data.person.unit.name}{data.person.campus ? `, ${data.person.campus.name}` : ''}</span>}
                    </p>
                    <div>
                        <p className="mb-1 text-xs font-semibold uppercase text-gray-500">Records</p>
                        {data.projects.length === 0 ? <p className="text-sm text-gray-500">No visible records.</p> : (
                            <ul className="space-y-2">
                                {data.projects.map((p) => (
                                    <li key={p.id} className="text-sm">
                                        <Link href={`/projects/${p.id}`} className="font-medium text-brand-700 hover:underline">{p.title}</Link>
                                        <span className="ml-2 inline-flex gap-1"><OfficeBadge office={p.office} />{p.verification_status === 'verified' ? <StageBadge stage={p.stage} /> : <VerificationBadge status={p.verification_status} />}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            )}
        </Modal>
    );
}
