'use client';

import { useRef, useState } from 'react';
import { errorMessage, openProtectedFile } from '@/lib/api';
import { formatBytes, formatDate } from '@/lib/format';
import type { Attachment } from '@/lib/types';

const DRIVE_LABEL: Record<Attachment['drive_status'], string> = {
    synced: 'Backed up to Google Drive',
    pending: 'Drive backup queued',
    failed: 'Drive backup failed (local copy safe)',
    disabled: 'Stored locally',
};

export function AttachmentList({ attachments }: { attachments: Attachment[] }) {
    const [error, setError] = useState<string | null>(null);

    const open = async (attachment: Attachment) => {
        setError(null);
        try {
            await openProtectedFile(`/attachments/${attachment.id}`);
        } catch (err) {
            setError(errorMessage(err, 'The file could not be opened.'));
        }
    };

    return (
        <div>
            <ul className="divide-y divide-gray-100 rounded-md border border-gray-200">
                {attachments.map((a) => (
                    <li key={a.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
                        <button onClick={() => open(a)} className="truncate text-left font-medium text-brand-700 hover:underline">
                            📄 {a.original_name}
                        </button>
                        <span className="text-xs text-gray-500">
                            {formatBytes(a.size)} · {formatDate(a.created_at)} · {a.source === 'drive_import' ? 'Imported from Drive' : DRIVE_LABEL[a.drive_status]}
                        </span>
                    </li>
                ))}
            </ul>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

/**
 * Multi-file picker for Means of Verification.
 */
export function FileInput({ files, onChange, required }: { files: File[]; onChange: (files: File[]) => void; required?: boolean }) {
    const input = useRef<HTMLInputElement>(null);

    return (
        <div>
            <div
                className="flex cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-600 hover:border-brand-500 hover:bg-brand-50"
                onClick={() => input.current?.click()}
                onDragOver={(e) => e.preventDefault()}
                onDrop={(e) => {
                    e.preventDefault();
                    onChange([...files, ...Array.from(e.dataTransfer.files)]);
                }}
            >
                <span className="font-medium text-brand-700">Click to attach files</span>
                <span className="text-xs text-gray-500">or drag them here · PDF, Word, Excel, PowerPoint, JPG, PNG · up to 20 MB each{required ? ' · required' : ''}</span>
            </div>
            <input
                ref={input}
                type="file"
                multiple
                className="hidden"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                onChange={(e) => {
                    onChange([...files, ...Array.from(e.target.files ?? [])]);
                    e.target.value = '';
                }}
            />
            {files.length > 0 && (
                <ul className="mt-2 space-y-1">
                    {files.map((file, index) => (
                        <li key={`${file.name}-${index}`} className="flex items-center justify-between rounded bg-gray-50 px-3 py-1.5 text-sm">
                            <span className="truncate">{file.name} <span className="text-xs text-gray-500">({formatBytes(file.size)})</span></span>
                            <button type="button" className="text-xs text-red-600 hover:underline" onClick={() => onChange(files.filter((_, i) => i !== index))}>
                                Remove
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
