'use client';

import { useState } from 'react';
import api, { errorMessage } from '@/lib/api';
import type { ProjectUpdate } from '@/lib/types';
import { AttachmentList } from './Attachments';
import { DuplicateMatches } from './ProjectFormParts';
import { Alert, Button, Field, Textarea } from './ui';
import { UpdateDetails } from './UpdateDetails';

/**
 * Verification Module action panel: check the submitted MOVs, then verify or return.
 */
export default function ReviewPanel({ update, onDone }: { update: ProjectUpdate; onDone: () => void }) {
    const [remarks, setRemarks] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    const act = async (action: 'verify' | 'return') => {
        setSaving(true);
        setError('');
        try {
            await api.post(`/verification/${update.id}/${action}`, { remarks: remarks || undefined });
            onDone();
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-4">
            <div>
                <p className="font-medium text-gray-900">{update.summary}</p>
                <p className="text-xs text-gray-500">Submitted by {update.submitter?.name}</p>
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
            <Field label="Remarks" hint="Required when returning; shown to the submitter.">
                <Textarea value={remarks} onChange={(e) => setRemarks(e.target.value)} placeholder="e.g., Attach the signed acceptance letter from the journal." />
            </Field>
            <div className="flex flex-wrap justify-end gap-2">
                <Button variant="secondary" disabled={saving} onClick={() => act('return')}>Return for correction</Button>
                <Button disabled={saving} onClick={() => act('verify')}>Verify and apply</Button>
            </div>
        </div>
    );
}
