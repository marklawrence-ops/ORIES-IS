'use client';

import { useParams } from 'next/navigation';
import { useApi } from '@/lib/hooks';
import { CERTIFICATE_LABELS, formatDate, OFFICE_LABELS, OUTCOME_LABELS, STAGE_LABELS } from '@/lib/format';
import type { Certificate } from '@/lib/types';
import { Alert, Button, Loading } from '@/components/ui';

const BODY: Record<Certificate['type'], (c: Certificate) => string> = {
    participation: (c) => `for having actively participated as ${c.project?.lead_id === c.user_id ? 'project leader' : 'a member'} in the ${c.project?.office} undertaking entitled`,
    completion: () => 'for the successful completion of the undertaking entitled',
    publication: () => 'for the publication of the output of the undertaking entitled',
    utilization: () => 'for the utilization of the output of the undertaking entitled',
};

export default function CertificatePrintPage() {
    const { id } = useParams<{ id: string }>();
    const { data, error, loading } = useApi<{ certificate: Certificate }>(`/certificates/${id}`);

    if (loading) return <Loading />;
    if (error || !data) return <Alert>{error ?? 'Certificate not found.'}</Alert>;

    const c = data.certificate;
    if (c.status !== 'issued') return <Alert tone="amber">This certificate has not been issued.</Alert>;

    const project = c.project!;
    const verifyUrl = typeof window !== 'undefined' ? `${window.location.origin}/verify/${c.verification_code}` : '';
    const relevantOutcomes = (project.outcomes ?? []).filter((o) =>
        c.type === 'publication' ? o.type === 'publication' : c.type === 'utilization' ? ['commercialization', 'policy', 'technology_transfer'].includes(o.type) : false,
    );

    return (
        <div>
            <div className="no-print mb-4 flex justify-end">
                <Button onClick={() => window.print()}>Print / Save as PDF</Button>
            </div>
            <div className="print-area mx-auto max-w-3xl border-8 border-double border-brand-700 bg-white px-12 py-14 text-center shadow-sm">
                <p className="text-sm uppercase tracking-[0.3em] text-gray-600">Republic of the Philippines</p>
                <p className="text-xl font-bold text-brand-800">EASTERN VISAYAS STATE UNIVERSITY</p>
                <p className="text-sm text-gray-700">Office of Research, Innovation, and Extension Services</p>
                <p className="text-xs text-gray-500">Tacloban City, Leyte</p>

                <h1 className="mt-10 font-serif text-4xl font-bold uppercase tracking-wide text-gray-900">{CERTIFICATE_LABELS[c.type]}</h1>
                <p className="mt-8 text-sm text-gray-700">This certificate is awarded to</p>
                <p className="mt-2 font-serif text-3xl font-semibold text-brand-800">{c.recipient?.name}</p>
                {c.recipient?.position && <p className="text-sm text-gray-600">{c.recipient.position}</p>}
                <p className="mx-auto mt-6 max-w-xl text-sm leading-relaxed text-gray-700">{BODY[c.type](c)}</p>
                <p className="mx-auto mt-2 max-w-xl font-serif text-xl font-semibold italic text-gray-900">“{project.title}”</p>
                <p className="mt-3 text-sm text-gray-700">
                    {OFFICE_LABELS[project.office]} · {STAGE_LABELS[project.stage]}
                    {project.start_date && ` · ${formatDate(project.start_date)} – ${project.completed_at ? formatDate(project.completed_at) : 'present'}`}
                </p>
                {relevantOutcomes.length > 0 && (
                    <ul className="mx-auto mt-3 max-w-xl text-sm text-gray-700">
                        {relevantOutcomes.map((o) => <li key={o.id}>{OUTCOME_LABELS[o.type]}: {o.title}{o.venue ? `, ${o.venue}` : ''} ({formatDate(o.date)})</li>)}
                    </ul>
                )}
                <p className="mt-6 text-sm text-gray-700">Given this {formatDate(c.issued_at)} at Eastern Visayas State University, Tacloban City.</p>

                <div className="mt-16 inline-block border-t border-gray-800 px-10 pt-1">
                    <p className="font-semibold uppercase text-gray-900">{c.issuer?.name}</p>
                    <p className="text-sm text-gray-600">{c.issuer?.position ?? 'Director'}</p>
                </div>

                <div className="mt-12 flex flex-wrap items-end justify-between gap-4 border-t border-gray-200 pt-4 text-left text-xs text-gray-500">
                    <div>
                        <p>Serial No.: <span className="font-mono text-gray-800">{c.serial_no}</span></p>
                        <p>Verification code: <span className="font-mono text-gray-800">{c.verification_code}</span></p>
                    </div>
                    <p className="max-w-xs text-right">Verify this certificate at<br /><span className="font-mono text-gray-800">{verifyUrl}</span></p>
                </div>
            </div>
        </div>
    );
}
