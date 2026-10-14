'use client';

import { useState } from 'react';
import api, { errorMessage } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { useApi } from '@/lib/hooks';
import { METRIC_LABELS, OFFICE_LABELS } from '@/lib/format';
import type { Office } from '@/lib/types';
import { Alert, Button, Card, Field, Input, Loading, PageHeader, Select } from '@/components/ui';

const METRICS = Object.keys(METRIC_LABELS);

export default function TargetsPage() {
    const { user, hasRole } = useAuth();
    const [year, setYear] = useState(new Date().getFullYear());
    const [office, setOffice] = useState<Office>(user.office ?? 'research');
    const { data, loading } = useApi<{ targets: { office: Office; metric: string; value: number }[] }>('/targets', { year });

    if (!hasRole('staff', 'director')) return <Alert>Only ORIES staff and directors set office targets.</Alert>;

    const initial = Object.fromEntries((data?.targets ?? []).filter((t) => t.office === office).map((t) => [t.metric, String(t.value)]));

    return (
        <div className="mx-auto max-w-3xl">
            <PageHeader title="Office Targets" description="Yearly targets are compared against verified accomplishments in the OPCR report." />
            <Card>
                <div className="mb-4 grid gap-3 sm:grid-cols-2">
                    <Field label="Year"><Input type="number" value={year} onChange={(e) => setYear(Number(e.target.value))} /></Field>
                    <Field label="Office">
                        <Select value={office} onChange={(e) => setOffice(e.target.value as Office)} disabled={!!user.office}>
                            {Object.entries(OFFICE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                        </Select>
                    </Field>
                </div>
                {loading ? <Loading /> : <TargetForm key={`${year}-${office}`} year={year} office={office} initial={initial} />}
            </Card>
        </div>
    );
}

function TargetForm({ year, office, initial }: { year: number; office: Office; initial: Record<string, string> }) {
    const [values, setValues] = useState<Record<string, string>>(initial);
    const [message, setMessage] = useState<{ tone: 'green' | 'red'; text: string } | null>(null);

    const save = async () => {
        setMessage(null);
        try {
            await api.put('/targets', { year, office, targets: Object.fromEntries(METRICS.map((m) => [m, values[m] ? Number(values[m]) : null])) });
            setMessage({ tone: 'green', text: 'Targets saved. They appear in the OPCR report.' });
        } catch (err) {
            setMessage({ tone: 'red', text: errorMessage(err) });
        }
    };

    return (
        <>
            <div className="grid gap-3 sm:grid-cols-2">
                {METRICS.map((m) => (
                    <Field key={m} label={METRIC_LABELS[m]}>
                        <Input type="number" min="0" value={values[m] ?? ''} onChange={(e) => setValues({ ...values, [m]: e.target.value })} placeholder="No target" />
                    </Field>
                ))}
            </div>
            {message && <div className="mt-4"><Alert tone={message.tone}>{message.text}</Alert></div>}
            <div className="mt-4 flex justify-end"><Button onClick={save}>Save targets</Button></div>
        </>
    );
}
