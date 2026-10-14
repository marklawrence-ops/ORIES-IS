'use client';

import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { Suspense, useState } from 'react';
import api, { errorMessage } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { useApi } from '@/lib/hooks';
import { CERTIFICATE_LABELS, formatDate, PURPOSE_LABELS } from '@/lib/format';
import type { Certificate, Paginated } from '@/lib/types';
import { Alert, Badge, Button, EmptyState, Field, Loading, Modal, PageHeader, Pagination, Select, Textarea } from '@/components/ui';

export default function CertificatesPage() {
    return (
        <Suspense fallback={<Loading />}>
            <CertificateList />
        </Suspense>
    );
}

function CertificateList() {
    const { hasRole } = useAuth();
    const searchParams = useSearchParams();
    const [status, setStatus] = useState(searchParams.get('status') ?? '');
    const [page, setPage] = useState(1);
    const { data, error, loading, reload } = useApi<Paginated<Certificate>>('/certificates', { status: status || undefined, page });
    const [deciding, setDeciding] = useState<Certificate | null>(null);
    const isDirector = hasRole('director');

    return (
        <div>
            <PageHeader
                title="Certificates"
                description={isDirector
                    ? 'Issue certificates for verified records of your office. Each issued certificate gets a serial number and a public verification code.'
                    : 'Request certificates from any verified record you are part of (open the record and choose “Request certificate”). Print issued certificates here.'}
            />
            <div className="mb-4 w-56">
                <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }}>
                    <option value="">All statuses</option>
                    <option value="requested">Requested</option>
                    <option value="issued">Issued</option>
                    <option value="declined">Declined</option>
                </Select>
            </div>

            {error && <Alert>{error}</Alert>}
            {loading && !data ? <Loading /> : data && data.data.length === 0 ? <EmptyState>No certificates yet.</EmptyState> : data && (
                <>
                    <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-600">
                                <tr>
                                    <th className="px-4 py-3">Certificate</th>
                                    <th className="px-4 py-3">Recipient</th>
                                    <th className="px-4 py-3">Record</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {data.data.map((c) => (
                                    <tr key={c.id}>
                                        <td className="px-4 py-3">
                                            <p className="font-medium">{CERTIFICATE_LABELS[c.type]}</p>
                                            <p className="text-xs text-gray-500">{PURPOSE_LABELS[c.purpose ?? 'other']}{c.serial_no ? ` · ${c.serial_no}` : ''}</p>
                                        </td>
                                        <td className="px-4 py-3">{c.recipient?.name}</td>
                                        <td className="px-4 py-3"><Link href={`/projects/${c.project_id}`} className="text-brand-700 hover:underline">{c.project?.title}</Link></td>
                                        <td className="px-4 py-3">
                                            <Badge tone={c.status === 'issued' ? 'green' : c.status === 'declined' ? 'red' : 'amber'}>{c.status}</Badge>
                                            <p className="text-xs text-gray-500">{formatDate(c.issued_at ?? c.created_at)}</p>
                                            {c.decision_remarks && <p className="text-xs text-gray-500">{c.decision_remarks}</p>}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {c.status === 'issued' && <Link href={`/certificates/${c.id}`} className="text-brand-700 hover:underline">View / print</Link>}
                                            {c.status === 'requested' && isDirector && <Button variant="secondary" onClick={() => setDeciding(c)}>Decide</Button>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination page={data.current_page} lastPage={data.last_page} onChange={setPage} />
                </>
            )}

            {deciding && <DecideModal certificate={deciding} onClose={() => setDeciding(null)} onDone={() => { setDeciding(null); reload(); }} />}
        </div>
    );
}

function DecideModal({ certificate, onClose, onDone }: { certificate: Certificate; onClose: () => void; onDone: () => void }) {
    const [remarks, setRemarks] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    const act = async (action: 'issue' | 'decline') => {
        setSaving(true);
        setError('');
        try {
            await api.post(`/certificates/${certificate.id}/${action}`, { remarks: remarks || undefined });
            onDone();
        } catch (err) {
            setError(errorMessage(err));
            setSaving(false);
        }
    };

    return (
        <Modal open title={CERTIFICATE_LABELS[certificate.type]} onClose={onClose}>
            <div className="space-y-4">
                <p className="text-sm text-gray-700">
                    <strong>{certificate.recipient?.name}</strong> requests this certificate for <em>{certificate.project?.title}</em> ({PURPOSE_LABELS[certificate.purpose ?? 'other']}).
                </p>
                {certificate.request_remarks && <p className="rounded bg-gray-50 px-3 py-2 text-sm">{certificate.request_remarks}</p>}
                <p className="text-sm"><Link href={`/projects/${certificate.project_id}`} target="_blank" className="text-brand-700 underline">Review the record&apos;s verified history and MOVs</Link></p>
                {error && <Alert>{error}</Alert>}
                <Field label="Remarks" hint="Required when declining."><Textarea value={remarks} onChange={(e) => setRemarks(e.target.value)} /></Field>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" disabled={saving} onClick={() => act('decline')}>Decline</Button>
                    <Button disabled={saving} onClick={() => act('issue')}>Issue certificate</Button>
                </div>
            </div>
        </Modal>
    );
}
