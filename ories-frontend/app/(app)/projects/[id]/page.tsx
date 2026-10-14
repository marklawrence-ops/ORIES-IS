'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useState, type FormEvent } from 'react';
import api, { errorMessage } from '@/lib/api';
import { useApi, useLookups } from '@/lib/hooks';
import { CERTIFICATE_LABELS, formatDate, formatPeso, INDEXING_LABELS, OUTCOME_LABELS, PURPOSE_LABELS, STAGE_LABELS } from '@/lib/format';
import type { OutcomeType, ProjectDetail, Stage } from '@/lib/types';
import { AttachmentList, FileInput } from '@/components/Attachments';
import ReviewPanel from '@/components/ReviewPanel';
import StageTracker from '@/components/StageTracker';
import { UpdateDetails } from '@/components/UpdateDetails';
import { Alert, Badge, Button, ButtonLink, Card, Field, Input, Loading, Modal, OfficeBadge, Select, StageBadge, Textarea, VerificationBadge } from '@/components/ui';

export default function ProjectDetailPage() {
    const { id } = useParams<{ id: string }>();
    const { data, error, loading, reload } = useApi<ProjectDetail>(`/projects/${id}`);
    const [modal, setModal] = useState<'update' | 'certificate' | null>(null);

    if (loading && !data) return <Loading />;
    if (error || !data) return <Alert>{error ?? 'Record not found.'}</Alert>;

    const { project, updates, permissions, activity } = data;
    const pending = updates.find((u) => u.status === 'pending');
    const returned = project.verification_status === 'returned' ? updates.find((u) => u.type === 'proposal' && u.status === 'returned') : null;

    return (
        <div className="space-y-6">
            <div>
                <Link href="/projects" className="text-sm text-brand-700 hover:underline">← Records</Link>
                <div className="mt-2 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">{project.title}</h1>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            <OfficeBadge office={project.office} />
                            <VerificationBadge status={project.verification_status} />
                            {project.verification_status === 'verified' && <StageBadge stage={project.stage} />}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {permissions.can_submit_update && <Button onClick={() => setModal('update')}>Submit update</Button>}
                        {permissions.can_request_certificate && <Button variant="secondary" onClick={() => setModal('certificate')}>Request certificate</Button>}
                        {permissions.can_resubmit && <ButtonLink href={`/projects/${project.id}/edit`}>Correct and resubmit</ButtonLink>}
                    </div>
                </div>
            </div>

            {returned && <Alert tone="amber"><strong>Returned for correction:</strong> {returned.review_remarks}</Alert>}
            {pending && permissions.is_involved && !permissions.can_verify && (
                <Alert tone="blue">“{pending.summary}” is awaiting verification by the ORIES {project.office} office. The record changes once it is verified.</Alert>
            )}

            {pending && permissions.can_verify && (
                <Card title="Verification required" className="border-amber-300">
                    <ReviewPanel update={pending} onDone={reload} />
                </Card>
            )}

            <Card>
                <StageTracker stage={project.stage} verification={project.verification_status} pendingStage={pending?.type === 'stage' ? pending.to_stage : null} />
            </Card>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card title="Details">
                        {project.abstract && <p className="mb-4 whitespace-pre-line text-sm text-gray-700">{project.abstract}</p>}
                        <dl className="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                            <Detail label="Lead">{project.lead?.name}</Detail>
                            <Detail label="Campus / unit">{project.campus?.name}{project.unit ? ` · ${project.unit.name}` : ''}</Detail>
                            <Detail label="Funding">{[project.funding_source, project.budget ? formatPeso(project.budget) : null].filter(Boolean).join(' · ') || '—'}</Detail>
                            <Detail label="Proposed">{formatDate(project.proposed_at)}</Detail>
                            <Detail label="Started">{formatDate(project.start_date)}</Detail>
                            <Detail label="Completed">{formatDate(project.completed_at)}</Detail>
                            {project.keywords && <Detail label="Keywords">{project.keywords}</Detail>}
                            <Detail label="Last verified">{formatDate(project.last_verified_at, true)}</Detail>
                        </dl>
                        <div className="mt-4">
                            <p className="text-xs font-semibold uppercase text-gray-500">Members</p>
                            <div className="mt-1 flex flex-wrap gap-2">
                                {project.members?.map((m) => (
                                    <Badge key={m.id} tone={m.role === 'lead' ? 'brand' : 'gray'}>{m.name}{m.role === 'lead' ? ' (lead)' : ''}{!m.user_id ? ' · external' : ''}</Badge>
                                ))}
                            </div>
                        </div>
                        {!!project.tags?.length && (
                            <div className="mt-3">
                                <p className="text-xs font-semibold uppercase text-gray-500">Credited units and partners</p>
                                <div className="mt-1 flex flex-wrap gap-2">
                                    {project.tags.map((t) => <Badge key={t.id} tone="green">{t.label}</Badge>)}
                                </div>
                            </div>
                        )}
                    </Card>

                    <Card title="Verified outcomes">
                        {!project.outcomes?.length ? <p className="text-sm text-gray-500">No outcomes recorded yet. Publications, commercialization, policy adoption, and technology transfer are added as updates.</p> : (
                            <ul className="divide-y divide-gray-100">
                                {project.outcomes.map((o) => (
                                    <li key={o.id} className="py-2 text-sm">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge tone="purple">{OUTCOME_LABELS[o.type]}</Badge>
                                            {o.indexing && <Badge tone={o.indexing === 'scopus' ? 'green' : 'gray'}>{INDEXING_LABELS[o.indexing]}</Badge>}
                                            <span className="text-gray-500">{formatDate(o.date)}</span>
                                        </div>
                                        <p className="mt-1 font-medium text-gray-900">{o.url ? <a href={o.url} target="_blank" rel="noopener noreferrer" className="hover:underline">{o.title}</a> : o.title}</p>
                                        {o.venue && <p className="text-gray-600">{o.venue}{o.citations != null ? ` · ${o.citations} citation(s)` : ''}</p>}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card title="Submission history and Means of Verification">
                        <ol className="relative space-y-5 border-l border-gray-200 pl-5">
                            {updates.map((u) => (
                                <li key={u.id} className="relative">
                                    <span className={`absolute -left-[27px] top-1 h-3 w-3 rounded-full border-2 border-white ${u.status === 'verified' ? 'bg-green-500' : u.status === 'returned' ? 'bg-red-500' : 'bg-amber-400'}`} />
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium text-gray-900">{u.summary}</span>
                                        <VerificationBadge status={u.status} />
                                    </div>
                                    <p className="text-xs text-gray-500">Submitted {formatDate(u.created_at, true)} by {u.submitter?.name}{u.reviewed_at && ` · reviewed ${formatDate(u.reviewed_at, true)} by ${u.reviewer?.name ?? 'ORIES'}`}</p>
                                    <div className="mt-2 space-y-2">
                                        <UpdateDetails update={u} />
                                        {u.review_remarks && <p className="rounded bg-gray-50 px-3 py-2 text-sm text-gray-700"><span className="font-medium">Reviewer remarks:</span> {u.review_remarks}</p>}
                                        {!!u.attachments?.length && <AttachmentList attachments={u.attachments} />}
                                        {u.attachments_hidden && <p className="text-xs text-gray-500">Files are visible once verified.</p>}
                                    </div>
                                </li>
                            ))}
                        </ol>
                        {data.imported_attachments.length > 0 && (
                            <div className="mt-4">
                                <p className="mb-1 text-sm font-medium text-gray-700">Files imported from Google Drive by ORIES staff</p>
                                <AttachmentList attachments={data.imported_attachments} />
                            </div>
                        )}
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card title="Certificates">
                        {!project.certificates?.length ? <p className="text-sm text-gray-500">None yet.</p> : (
                            <ul className="space-y-2 text-sm">
                                {project.certificates.map((c) => (
                                    <li key={c.id}>
                                        <Link href={`/certificates/${c.id}`} className="font-medium text-brand-700 hover:underline">{CERTIFICATE_LABELS[c.type]}</Link>
                                        <p className="text-xs text-gray-500">{c.recipient?.name} · {c.status}{c.serial_no ? ` · ${c.serial_no}` : ''}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                    {activity.length > 0 && (
                        <Card title="Activity timeline">
                            <ul className="space-y-3 text-sm">
                                {activity.map((a) => (
                                    <li key={a.id}>
                                        <p className="text-gray-800">{a.description}</p>
                                        <p className="text-xs text-gray-500">{a.user?.name} · {formatDate(a.created_at, true)}</p>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}
                </div>
            </div>

            {modal === 'update' && <SubmitUpdateModal projectId={project.id} nextStages={permissions.next_stages} onClose={() => setModal(null)} onDone={() => { setModal(null); reload(); }} />}
            {modal === 'certificate' && <RequestCertificateModal projectId={project.id} onClose={() => setModal(null)} onDone={() => { setModal(null); reload(); }} />}
        </div>
    );
}

function Detail({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-gray-500">{label}</dt>
            <dd className="text-gray-900">{children}</dd>
        </div>
    );
}

function SubmitUpdateModal({ projectId, nextStages, onClose, onDone }: { projectId: number; nextStages: Stage[]; onClose: () => void; onDone: () => void }) {
    const lookups = useLookups();
    const [type, setType] = useState<'stage' | 'outcome'>(nextStages.length ? 'stage' : 'outcome');
    const [toStage, setToStage] = useState<Stage | ''>(nextStages[0] ?? '');
    const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
    const [outcome, setOutcome] = useState({ type: 'publication' as OutcomeType, title: '', venue: '', indexing: '', date: new Date().toISOString().slice(0, 10), url: '', citations: '' });
    const [remarks, setRemarks] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setError('');
        const body = new FormData();
        body.append('type', type);
        if (type === 'stage') {
            body.append('to_stage', toStage);
            body.append('date', date);
        } else {
            Object.entries(outcome).forEach(([k, v]) => v !== '' && body.append(`outcome[${k}]`, v));
        }
        if (remarks) body.append('remarks', remarks);
        files.forEach((f) => body.append('files[]', f));
        try {
            await api.post(`/projects/${projectId}/updates`, body);
            onDone();
        } catch (err) {
            setError(errorMessage(err));
            setSaving(false);
        }
    };

    const isPublication = outcome.type === 'publication' || outcome.type === 'presentation';

    return (
        <Modal open title="Submit an update" onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                {error && <Alert>{error}</Alert>}
                <Field label="What changed?">
                    <Select value={type} onChange={(e) => setType(e.target.value as 'stage' | 'outcome')}>
                        {nextStages.length > 0 && <option value="stage">The stage moved forward</option>}
                        <option value="outcome">A new outcome (publication, commercialization, policy, technology transfer)</option>
                    </Select>
                </Field>
                {type === 'stage' ? (
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="New stage">
                            <Select value={toStage} onChange={(e) => setToStage(e.target.value as Stage)}>
                                {nextStages.map((s) => <option key={s} value={s}>{STAGE_LABELS[s]}</option>)}
                            </Select>
                        </Field>
                        <Field label="Effective date"><Input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></Field>
                    </div>
                ) : (
                    <div className="space-y-3">
                        <Field label="Outcome type">
                            <Select value={outcome.type} onChange={(e) => setOutcome({ ...outcome, type: e.target.value as OutcomeType })}>
                                {(lookups?.outcome_types ?? []).map((o) => <option key={o} value={o}>{OUTCOME_LABELS[o]}</option>)}
                            </Select>
                        </Field>
                        <Field label={isPublication ? 'Article / paper title' : 'Description'}>
                            <Input required value={outcome.title} onChange={(e) => setOutcome({ ...outcome, title: e.target.value })} />
                        </Field>
                        <Field label={isPublication ? 'Journal or conference' : 'Adopting agency, partner, or company'}>
                            <Input value={outcome.venue} onChange={(e) => setOutcome({ ...outcome, venue: e.target.value })} />
                        </Field>
                        <div className="grid grid-cols-2 gap-3">
                            {outcome.type === 'publication' && (
                                <Field label="Indexing">
                                    <Select value={outcome.indexing} onChange={(e) => setOutcome({ ...outcome, indexing: e.target.value })}>
                                        <option value="">Select…</option>
                                        {(lookups?.indexing ?? []).map((i) => <option key={i} value={i}>{INDEXING_LABELS[i]}</option>)}
                                    </Select>
                                </Field>
                            )}
                            <Field label="Date"><Input type="date" value={outcome.date} onChange={(e) => setOutcome({ ...outcome, date: e.target.value })} /></Field>
                            {outcome.type === 'publication' && (
                                <Field label="Citations (optional)"><Input type="number" min="0" value={outcome.citations} onChange={(e) => setOutcome({ ...outcome, citations: e.target.value })} /></Field>
                            )}
                            <Field label="Link (DOI or URL)"><Input type="url" value={outcome.url} onChange={(e) => setOutcome({ ...outcome, url: e.target.value })} /></Field>
                        </div>
                    </div>
                )}
                <Field label="Means of Verification" hint={type === 'stage' ? 'e.g., notice to proceed, terminal report, approval memo' : 'e.g., published article, MOA, ordinance, licensing agreement'}>
                    <FileInput files={files} onChange={setFiles} required />
                </Field>
                <Field label="Note to the verifier (optional)"><Textarea value={remarks} onChange={(e) => setRemarks(e.target.value)} /></Field>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancel</Button>
                    <Button type="submit" disabled={saving || files.length === 0}>{saving ? 'Submitting…' : 'Submit for verification'}</Button>
                </div>
            </form>
        </Modal>
    );
}

function RequestCertificateModal({ projectId, onClose, onDone }: { projectId: number; onClose: () => void; onDone: () => void }) {
    const lookups = useLookups();
    const [type, setType] = useState('participation');
    const [purpose, setPurpose] = useState('promotion');
    const [remarks, setRemarks] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setError('');
        try {
            await api.post('/certificates', { project_id: projectId, type, purpose, remarks: remarks || undefined });
            onDone();
        } catch (err) {
            setError(errorMessage(err));
            setSaving(false);
        }
    };

    return (
        <Modal open title="Request a certificate" onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                {error && <Alert>{error}</Alert>}
                <Field label="Certificate">
                    <Select value={type} onChange={(e) => setType(e.target.value)}>
                        {(lookups?.certificate_types ?? []).map((t) => <option key={t} value={t}>{CERTIFICATE_LABELS[t]}</option>)}
                    </Select>
                </Field>
                <Field label="Purpose">
                    <Select value={purpose} onChange={(e) => setPurpose(e.target.value)}>
                        {(lookups?.certificate_purposes ?? []).map((p) => <option key={p} value={p}>{PURPOSE_LABELS[p]}</option>)}
                    </Select>
                </Field>
                <Field label="Remarks (optional)"><Textarea value={remarks} onChange={(e) => setRemarks(e.target.value)} /></Field>
                <p className="text-xs text-gray-500">The director of this record&apos;s office issues the certificate based on its verified history and MOVs.</p>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancel</Button>
                    <Button type="submit" disabled={saving}>{saving ? 'Requesting…' : 'Request'}</Button>
                </div>
            </form>
        </Modal>
    );
}
