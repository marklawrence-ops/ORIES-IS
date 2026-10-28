'use client';

import { useRouter } from 'next/navigation';
import { useState } from 'react';
import api, { errorMessage } from '@/lib/api';
import { formatDate } from '@/lib/format';
import type { ProjectUpdate } from '@/lib/types';
import { AttachmentList } from './Attachments';
import { DuplicateMatches } from './ProjectFormParts';
import { Alert, Button, Field, Textarea } from './ui';
import { UpdateDetails } from './UpdateDetails';

// One-click starting points for the most common reasons a submission is returned
const RETURN_TEMPLATES = [
    'The Means of Verification is missing or cannot be opened. Attach a clear, complete copy.',
    'The attached file does not match this update. Attach the document that supports it.',
    'The document is unsigned. Attach the signed or approved copy.',
    'The dates entered do not match the attached document. Correct them to match.',
    'The members or credited units do not match the attached document.',
];

/**
 * Verification Module action panel: check the submitted MOVs, then verify or return.
 * Afterwards the next item in the reviewer's queue opens on its own.
 */
export default function ReviewPanel({ update, onDone }: { update: ProjectUpdate; onDone: (message: string) => void }) {
    const router = useRouter();
    const [remarks, setRemarks] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    const act = async (action: 'verify' | 'return') => {
        setSaving(true);
        setError('');
        try {
            const res = await api.post<{ next: { project_id: number; remaining: number } | null }>(`/verification/${update.id}/${action}`, { remarks: remarks || undefined });
            const next = res.data.next;
            if (next) {
                router.push(`/projects/${next.project_id}?reviewed=${action}&remaining=${next.remaining}`);
            } else {
                setSaving(false);
                onDone(action === 'verify' ? 'Update verified. Your verification queue is now empty.' : 'Update returned. Your verification queue is now empty.');
            }
        } catch (err) {
            setError(errorMessage(err));
            setSaving(false);
        }
    };

    return (
        <div className="space-y-4">
            <div>
                <p className="font-medium text-gray-900">{update.summary}</p>
                <p className="text-xs text-gray-500">Submitted by {update.submitter?.name} on {formatDate(update.created_at, true)}</p>
            </div>
            <UpdateDetails update={update} />
            {update.duplicate_matches && update.duplicate_matches.length > 0 && (
                <Alert tone="amber">
                    <p className="font-medium">Crosschecking Module: possible duplicate title</p>
                    <p className="text-xs">Decide whether this is the same undertaking before verifying.</p>
                    <DuplicateMatches matches={update.duplicate_matches} />
                </Alert>
            )}
            {update.attachments && update.attachments.length > 0 ? (
                <div>
                    <p className="mb-1 text-sm font-medium text-gray-700">Means of Verification — open each file to check it</p>
                    <AttachmentList attachments={update.attachments} />
                </div>
            ) : (
                <Alert tone="amber">No files were attached to this update.</Alert>
            )}
            {error && <Alert>{error}</Alert>}
            <Field label="Remarks" hint="Required when returning; shown to the submitter and the team.">
                <Textarea value={remarks} onChange={(e) => setRemarks(e.target.value)} placeholder="e.g., Attach the signed acceptance letter from the journal." />
            </Field>
            <div>
                <p className="mb-1 text-xs text-gray-500">Common reasons for returning (click to use, then edit if needed):</p>
                <div className="flex flex-wrap gap-1.5">
                    {RETURN_TEMPLATES.map((text) => (
                        <button
                            key={text}
                            type="button"
                            onClick={() => setRemarks((r) => (r.trim() ? `${r.trim()} ${text}` : text))}
                            className="rounded-full border border-gray-300 bg-white px-2.5 py-1 text-left text-xs text-gray-700 hover:border-brand-500 hover:text-brand-700"
                        >
                            {text}
                        </button>
                    ))}
                </div>
            </div>
            <div className="flex flex-wrap justify-end gap-2">
                <Button variant="secondary" disabled={saving} onClick={() => act('return')}>Return for correction</Button>
                <Button disabled={saving} onClick={() => act('verify')}>Verify and apply</Button>
            </div>
        </div>
    );
}
