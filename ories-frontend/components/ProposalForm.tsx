'use client';

import { useRouter } from 'next/navigation';
import { useState, type FormEvent } from 'react';
import api, { errorMessage } from '@/lib/api';
import { useLookups } from '@/lib/hooks';
import { OFFICE_LABELS } from '@/lib/format';
import type { Office, Project } from '@/lib/types';
import { FileInput } from './Attachments';
import { DuplicateWarning, MemberPicker, TagPicker, type MemberDraft, type TagDraft } from './ProjectFormParts';
import { Alert, Button, Card, Field, Input, Select, Textarea } from './ui';

/**
 * Used both to submit a new proposal and to correct a returned one.
 */
export default function ProposalForm({ existing, returnRemarks }: { existing?: Project; returnRemarks?: string | null }) {
    const router = useRouter();
    const lookups = useLookups();
    const [form, setForm] = useState({
        title: existing?.title ?? '',
        office: (existing?.office ?? 'research') as Office,
        abstract: existing?.abstract ?? '',
        keywords: existing?.keywords ?? '',
        funding_source: existing?.funding_source ?? '',
        budget: existing?.budget ?? '',
        start_date: existing?.start_date ?? '',
        end_date: existing?.end_date ?? '',
        proposed_at: existing?.proposed_at ?? new Date().toISOString().slice(0, 10),
        remarks: '',
    });
    const [members, setMembers] = useState<MemberDraft[]>(
        existing?.members?.filter((m) => m.role !== 'lead').map((m) => ({ user_id: m.user_id ?? undefined, name: m.name })) ?? [],
    );
    const [tags, setTags] = useState<TagDraft[]>(
        existing?.tags?.map((t) => ({ type: t.type, unit_id: t.unit_id ?? undefined, campus_id: t.campus_id ?? undefined, label: t.label })) ?? [],
    );
    const [files, setFiles] = useState<File[]>([]);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }));

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        if (!existing && files.length === 0) {
            setError('Attach the proposal document as the Means of Verification.');
            return;
        }
        setError('');
        setSaving(true);

        const body = new FormData();
        Object.entries(form).forEach(([k, v]) => v !== '' && body.append(k, v));
        members.forEach((m, i) => {
            if (m.user_id) body.append(`members[${i}][user_id]`, String(m.user_id));
            body.append(`members[${i}][name]`, m.name);
        });
        tags.forEach((t, i) => {
            body.append(`tags[${i}][type]`, t.type);
            if (t.unit_id) body.append(`tags[${i}][unit_id]`, String(t.unit_id));
            if (t.campus_id) body.append(`tags[${i}][campus_id]`, String(t.campus_id));
            body.append(`tags[${i}][label]`, t.label);
        });
        files.forEach((f) => body.append('files[]', f));

        try {
            const res = await api.post(existing ? `/projects/${existing.id}` : '/projects', body);
            router.push(`/projects/${existing?.id ?? res.data.project.id}`);
        } catch (err) {
            setError(errorMessage(err));
            setSaving(false);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            {returnRemarks && <Alert tone="amber"><strong>Returned by ORIES:</strong> {returnRemarks}</Alert>}
            {error && <Alert>{error}</Alert>}

            <Card title="1. Proposal details">
                <div className="space-y-4">
                    <Field label="Title">
                        <Input required value={form.title} onChange={(e) => set('title', e.target.value)} placeholder="Full title of the research, innovation, or extension proposal" />
                        <DuplicateWarning title={form.title} ignoreId={existing?.id} />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label="ORIES office">
                            <Select value={form.office} onChange={(e) => set('office', e.target.value)}>
                                {(lookups?.offices ?? ['research', 'innovation', 'extension']).map((o) => <option key={o} value={o}>{OFFICE_LABELS[o]}</option>)}
                            </Select>
                        </Field>
                        <Field label="Date proposed"><Input type="date" value={form.proposed_at} onChange={(e) => set('proposed_at', e.target.value)} /></Field>
                        <Field label="Keywords" hint="Comma-separated"><Input value={form.keywords} onChange={(e) => set('keywords', e.target.value)} /></Field>
                    </div>
                    <Field label="Abstract / brief description"><Textarea rows={4} value={form.abstract} onChange={(e) => set('abstract', e.target.value)} /></Field>
                    <div className="grid gap-4 sm:grid-cols-4">
                        <Field label="Funding source"><Input value={form.funding_source} onChange={(e) => set('funding_source', e.target.value)} placeholder="e.g., EVSU Internal Fund" /></Field>
                        <Field label="Budget (PHP)"><Input type="number" min="0" step="0.01" value={form.budget} onChange={(e) => set('budget', e.target.value)} /></Field>
                        <Field label="Target start"><Input type="date" value={form.start_date} onChange={(e) => set('start_date', e.target.value)} /></Field>
                        <Field label="Target end"><Input type="date" value={form.end_date} onChange={(e) => set('end_date', e.target.value)} /></Field>
                    </div>
                </div>
            </Card>

            <Card title="2. Members and contributor tagging">
                <div className="space-y-4">
                    <Field label="Co-proponents / members" hint="You are recorded as the lead. Members with accounts receive credit in their dashboards and reports.">
                        <MemberPicker members={members} onChange={setMembers} />
                    </Field>
                    <Field label="Credited colleges, campuses, and partner agencies">
                        <TagPicker tags={tags} onChange={setTags} campuses={lookups?.campuses ?? []} />
                    </Field>
                </div>
            </Card>

            <Card title="3. Means of Verification">
                <div className="space-y-4">
                    <Field label={existing ? 'Additional or corrected files' : 'Proposal document and supporting files'}>
                        <FileInput files={files} onChange={setFiles} required={!existing} />
                    </Field>
                    <Field label="Note to the ORIES verifier (optional)"><Textarea value={form.remarks} onChange={(e) => set('remarks', e.target.value)} /></Field>
                    <Alert tone="blue">Files are stored on the university server and become visible to others only after ORIES staff verify them. You can always view your own uploads.</Alert>
                </div>
            </Card>

            <div className="flex justify-end gap-3">
                <Button type="button" variant="secondary" onClick={() => router.back()}>Cancel</Button>
                <Button type="submit" disabled={saving}>{saving ? 'Submitting…' : existing ? 'Resubmit for verification' : 'Submit for verification'}</Button>
            </div>
        </form>
    );
}
