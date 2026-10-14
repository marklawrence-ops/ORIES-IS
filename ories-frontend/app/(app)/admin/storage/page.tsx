'use client';

import { useState } from 'react';
import api, { errorMessage } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { useApi } from '@/lib/hooks';
import { formatBytes, formatDate } from '@/lib/format';
import type { Paginated, Project } from '@/lib/types';
import { Alert, Badge, Button, Card, Input, Loading, Modal, PageHeader } from '@/components/ui';

interface StorageStatus {
    configured: boolean;
    backup_enabled: boolean;
    local_bytes: number;
    attachments: { drive_status: string; total: number; bytes: number | null }[];
}

interface DriveFile {
    id: string;
    name: string;
    mime_type: string | null;
    size: number | null;
    url: string | null;
    created_at: string | null;
    imported_to: number | null;
}

const STATUS_LABEL: Record<string, string> = {
    synced: 'Backed up to Google Drive',
    pending: 'Backup queued',
    failed: 'Backup failed (will retry)',
    disabled: 'Local only (backup off)',
};

export default function StoragePage() {
    const { hasRole } = useAuth();
    const status = useApi<StorageStatus>(hasRole('staff') ? '/drive/status' : null);
    const [files, setFiles] = useState<DriveFile[] | null>(null);
    const [error, setError] = useState('');
    const [loadingFiles, setLoadingFiles] = useState(false);
    const [importing, setImporting] = useState<DriveFile | null>(null);

    if (!hasRole('staff')) return <Alert>Only ORIES staff manage storage.</Alert>;

    const loadFiles = async () => {
        setLoadingFiles(true);
        setError('');
        try {
            const res = await api.get('/drive/files');
            setFiles(res.data.files);
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setLoadingFiles(false);
        }
    };

    return (
        <div className="space-y-6">
            <PageHeader
                title="Storage and Google Drive Import"
                description="Means of Verification are stored on the university's own server first, so submissions keep working when cloud services are down. Google Drive serves as a secondary backup and as the source of files offices already keep there."
            />

            <Card title="Storage status">
                {status.loading || !status.data ? <Loading /> : (
                    <div className="space-y-3 text-sm">
                        <p>Local storage used: <strong>{formatBytes(status.data.local_bytes)}</strong></p>
                        <p>
                            Google Drive: {status.data.configured ? <Badge tone="green">connected</Badge> : <Badge tone="gray">not configured</Badge>}{' '}
                            · Automatic backup: {status.data.backup_enabled ? <Badge tone="green">on</Badge> : <Badge tone="gray">off</Badge>}
                        </p>
                        <ul className="grid gap-2 sm:grid-cols-4">
                            {status.data.attachments.map((a) => (
                                <li key={a.drive_status} className="rounded border border-gray-200 p-3">
                                    <p className="text-2xl font-bold">{a.total}</p>
                                    <p className="text-xs text-gray-600">{STATUS_LABEL[a.drive_status] ?? a.drive_status} · {formatBytes(Number(a.bytes ?? 0))}</p>
                                </li>
                            ))}
                        </ul>
                        <p className="text-xs text-gray-500">
                            When local storage approaches capacity, coordinate with the ICT Office for additional server or external storage. Failed backups are retried hourly by the scheduler.
                        </p>
                    </div>
                )}
            </Card>

            <Card title="Import existing files from Google Drive" actions={<Button variant="secondary" onClick={loadFiles} disabled={loadingFiles}>{loadingFiles ? 'Loading…' : 'Load Drive folder'}</Button>}>
                <p className="mb-3 text-sm text-gray-600">Bring files from the shared ORIES drop-off folder into the local repository and attach each to its verified record.</p>
                {error && <Alert>{error}</Alert>}
                {files && files.length === 0 && <p className="text-sm text-gray-500">The Drive folder is empty.</p>}
                {files && files.length > 0 && (
                    <ul className="divide-y divide-gray-100">
                        {files.map((f) => (
                            <li key={f.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                <div>
                                    <p className="font-medium">{f.name}</p>
                                    <p className="text-xs text-gray-500">{formatDate(f.created_at)}{f.size ? ` · ${formatBytes(f.size)}` : ''}</p>
                                </div>
                                <div className="flex gap-2">
                                    {f.url && <a href={f.url} target="_blank" rel="noopener noreferrer" className="text-sm text-brand-700 hover:underline">Preview</a>}
                                    {f.imported_to ? <Badge tone="green">Imported to record #{f.imported_to}</Badge> : <Button onClick={() => setImporting(f)}>Import</Button>}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            {importing && <ImportModal file={importing} onClose={() => setImporting(null)} onDone={() => { setImporting(null); loadFiles(); status.reload(); }} />}
        </div>
    );
}

function ImportModal({ file, onClose, onDone }: { file: DriveFile; onClose: () => void; onDone: () => void }) {
    const [query, setQuery] = useState(file.name.replace(/\.[^.]+$/, ''));
    const { data, loading } = useApi<Paginated<Project>>('/projects', { q: query, scope: 'responsibility', per_page: 8 });
    const [error, setError] = useState('');

    const importTo = async (project: Project) => {
        try {
            await api.post('/drive/import', { file_id: file.id, project_id: project.id });
            onDone();
        } catch (err) {
            setError(errorMessage(err));
        }
    };

    return (
        <Modal open title={`Attach “${file.name}” to a record`} onClose={onClose}>
            <div className="space-y-3">
                <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search records…" />
                {error && <Alert>{error}</Alert>}
                {loading ? <Loading /> : (
                    <ul className="divide-y">
                        {data?.data.length === 0 && <li className="py-3 text-sm text-gray-500">No matching records in your office.</li>}
                        {data?.data.map((p) => (
                            <li key={p.id} className="flex items-center justify-between gap-2 py-2 text-sm">
                                <span>{p.title}</span>
                                <Button variant="secondary" onClick={() => importTo(p)}>Attach</Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Modal>
    );
}
