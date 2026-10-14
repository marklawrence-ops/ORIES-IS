'use client';

import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { useEffect, useState, type FormEvent } from 'react';
import api from '@/lib/api';
import { CERTIFICATE_LABELS, formatDate, OFFICE_LABELS } from '@/lib/format';
import type { Certificate } from '@/lib/types';
import { Alert, Button, Card, Input } from '@/components/ui';

/**
 * Public page where an HR office or evaluator confirms that a presented certificate is genuine.
 */
export default function VerifyCertificatePage() {
    const params = useParams<{ code?: string[] }>();
    const router = useRouter();
    const code = params.code?.[0] ?? '';
    const [input, setInput] = useState(code);
    const [result, setResult] = useState<{ valid: boolean; certificate?: Certificate } | null>(null);

    useEffect(() => {
        if (!code) return;
        api.get(`/certificates/verify/${encodeURIComponent(code)}`)
            .then((res) => setResult(res.data))
            .catch(() => setResult({ valid: false }));
    }, [code]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (input.trim()) router.push(`/verify/${input.trim().toUpperCase()}`);
    };

    const cert = result?.certificate;

    return (
        <div className="mx-auto w-full max-w-xl p-6">
            <h1 className="text-2xl font-bold text-gray-900">Certificate Verification</h1>
            <p className="mb-6 text-sm text-gray-600">Enter the verification code printed on an ORIES-issued certificate.</p>
            <form onSubmit={submit} className="mb-6 flex gap-2">
                <Input value={input} onChange={(e) => setInput(e.target.value)} placeholder="e.g., 7KQ2M9XA4P1B" className="font-mono uppercase" />
                <Button type="submit">Verify</Button>
            </form>

            {result && !result.valid && <Alert>No issued certificate matches this code. It may be mistyped or the certificate may not be genuine.</Alert>}
            {result?.valid && cert && (
                <Card title={<span className="text-green-700">✓ Valid certificate</span>}>
                    <dl className="grid grid-cols-3 gap-y-2 text-sm">
                        <dt className="text-gray-500">Serial no.</dt><dd className="col-span-2 font-mono">{cert.serial_no}</dd>
                        <dt className="text-gray-500">Type</dt><dd className="col-span-2">{CERTIFICATE_LABELS[cert.type]}</dd>
                        <dt className="text-gray-500">Awarded to</dt><dd className="col-span-2 font-medium">{cert.recipient?.name}</dd>
                        <dt className="text-gray-500">Record</dt><dd className="col-span-2">{cert.project?.title}</dd>
                        <dt className="text-gray-500">Office</dt><dd className="col-span-2">{cert.project ? OFFICE_LABELS[cert.project.office] : ''}</dd>
                        <dt className="text-gray-500">Issued</dt><dd className="col-span-2">{formatDate(cert.issued_at)} by {cert.issuer?.name}</dd>
                    </dl>
                </Card>
            )}
            <p className="mt-6 text-center text-sm"><Link href="/" className="text-brand-700 hover:underline">Go to ORIES-IS sign in</Link></p>
        </div>
    );
}
