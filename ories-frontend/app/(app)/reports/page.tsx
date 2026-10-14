'use client';

import { useState } from 'react';
import api, { errorMessage, openProtectedFile } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { useLookups } from '@/lib/hooks';
import { formatDate, INDEXING_LABELS, OFFICE_LABELS, OUTCOME_LABELS, STAGE_LABELS } from '@/lib/format';
import type { Report, ReportCell } from '@/lib/types';
import { Alert, Button, Card, Field, Input, PageHeader, Select } from '@/components/ui';

const TYPES = [
    { key: 'summary', label: 'Accomplishment summary', hint: 'Counts per year, quarter, or month: proposals, ongoing, completed, publications, Scopus, utilization, certificates.' },
    { key: 'projects', label: 'List of records', hint: 'Every verified research, innovation, and extension record active in the period.' },
    { key: 'outcomes', label: 'Publications and utilization', hint: 'Publications (with indexing and citations), commercialization, policy adoption, technology transfer.' },
    { key: 'ipcr', label: 'Individual accomplishments (IPCR)', hint: 'Per-faculty counts to support Individual Performance Commitment and Review.' },
    { key: 'opcr', label: 'Office targets vs. accomplishments (OPCR)', hint: 'Compares each office’s yearly targets with verified accomplishments.' },
    { key: 'ched', label: 'Institutional summary by campus (CHED)', hint: 'Campus-by-office totals for CHED and other external reporting.' },
    { key: 'certificates', label: 'Issued certificates', hint: 'Certificates issued in the period with serial numbers.' },
];

const RANGES = [
    { key: 'this_year', label: 'This year' },
    { key: 'last_3_years', label: 'Last 3 years' },
    { key: 'last_5_years', label: 'Last 5 years' },
    { key: 'last_10_years', label: 'Last 10 years' },
    { key: 'custom', label: 'Custom range' },
];

export default function ReportsPage() {
    const { hasRole } = useAuth();
    const lookups = useLookups();
    const [type, setType] = useState('summary');
    const [filters, setFilters] = useState({ range: 'this_year', from: '', to: '', group_by: 'year', office: '', campus_id: '', unit_id: '', stage: '', outcome: '', indexing: '', year: String(new Date().getFullYear()) });
    const [report, setReport] = useState<Report | null>(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const set = (key: keyof typeof filters, value: string) => setFilters((f) => ({ ...f, [key]: value }));
    const units = lookups?.campuses.find((c) => String(c.id) === filters.campus_id)?.units ?? [];
    const params = () => Object.fromEntries(Object.entries(filters).filter(([k, v]) => v !== '' && (filters.range === 'custom' || (k !== 'from' && k !== 'to'))));

    const generate = async () => {
        setLoading(true);
        setError('');
        try {
            const res = await api.get<Report>(`/reports/${type}`, { params: params() });
            setReport(res.data);
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setLoading(false);
        }
    };

    const exportCsv = async () => {
        try {
            await openProtectedFile(`/reports/${type}`, `ories-${type}-report.csv`, { ...params(), format: 'csv' });
        } catch (err) {
            setError(errorMessage(err));
        }
    };

    const current = TYPES.find((t) => t.key === type)!;

    return (
        <div>
            <PageHeader title="Reports" description="Generate reports from verified records only. Results follow your access level; choose the part of the data you need, then print or export." />

            <Card className="no-print mb-6">
                <div className="grid gap-4 md:grid-cols-3">
                    <Field label="Report" hint={current.hint}>
                        <Select value={type} onChange={(e) => { setType(e.target.value); setReport(null); }}>
                            {TYPES.map((t) => <option key={t.key} value={t.key}>{t.label}</option>)}
                        </Select>
                    </Field>
                    {type === 'opcr' ? (
                        <Field label="Target year"><Input type="number" value={filters.year} onChange={(e) => set('year', e.target.value)} /></Field>
                    ) : (
                        <Field label="Period">
                            <Select value={filters.range} onChange={(e) => set('range', e.target.value)}>
                                {RANGES.map((r) => <option key={r.key} value={r.key}>{r.label}</option>)}
                            </Select>
                        </Field>
                    )}
                    {type === 'summary' && (
                        <Field label="Group by">
                            <Select value={filters.group_by} onChange={(e) => set('group_by', e.target.value)}>
                                <option value="year">Yearly</option>
                                <option value="quarter">Quarterly</option>
                                <option value="month">Monthly</option>
                            </Select>
                        </Field>
                    )}
                    {filters.range === 'custom' && type !== 'opcr' && (
                        <>
                            <Field label="From"><Input type="date" value={filters.from} onChange={(e) => set('from', e.target.value)} /></Field>
                            <Field label="To"><Input type="date" value={filters.to} onChange={(e) => set('to', e.target.value)} /></Field>
                        </>
                    )}
                </div>
                <div className="mt-4 grid grid-cols-2 gap-3 md:grid-cols-5">
                    <Select value={filters.office} onChange={(e) => set('office', e.target.value)}>
                        <option value="">All offices</option>
                        {lookups?.offices.map((o) => <option key={o} value={o}>{OFFICE_LABELS[o]}</option>)}
                    </Select>
                    {!hasRole('faculty') && (
                        <>
                            <Select value={filters.campus_id} onChange={(e) => setFilters((f) => ({ ...f, campus_id: e.target.value, unit_id: '' }))}>
                                <option value="">All campuses</option>
                                {lookups?.campuses.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </Select>
                            <Select value={filters.unit_id} onChange={(e) => set('unit_id', e.target.value)} disabled={!filters.campus_id}>
                                <option value="">All colleges/units</option>
                                {units.map((u) => <option key={u.id} value={u.id}>{u.code ?? u.name}</option>)}
                            </Select>
                        </>
                    )}
                    {type === 'projects' && (
                        <Select value={filters.stage} onChange={(e) => set('stage', e.target.value)}>
                            <option value="">All stages</option>
                            {lookups?.stages.map((s) => <option key={s} value={s}>{STAGE_LABELS[s]}</option>)}
                        </Select>
                    )}
                    {type === 'outcomes' && (
                        <>
                            <Select value={filters.outcome} onChange={(e) => set('outcome', e.target.value)}>
                                <option value="">All outcome types</option>
                                {lookups?.outcome_types.map((o) => <option key={o} value={o}>{OUTCOME_LABELS[o]}</option>)}
                            </Select>
                            <Select value={filters.indexing} onChange={(e) => set('indexing', e.target.value)}>
                                <option value="">Any indexing</option>
                                {lookups?.indexing.map((i) => <option key={i} value={i}>{INDEXING_LABELS[i]}</option>)}
                            </Select>
                        </>
                    )}
                </div>
                <div className="mt-4 flex flex-wrap justify-end gap-2">
                    <Button onClick={generate} disabled={loading}>{loading ? 'Generating…' : 'Generate report'}</Button>
                    {report && <Button variant="secondary" onClick={exportCsv}>Export CSV (Excel)</Button>}
                    {report && <Button variant="secondary" onClick={() => window.print()}>Print / PDF</Button>}
                </div>
            </Card>

            {error && <Alert>{error}</Alert>}
            {report && <ReportView report={report} />}
        </div>
    );
}

function ReportView({ report }: { report: Report }) {
    const display = (value: ReportCell, key: string) => {
        if (value === null || value === '') return '—';
        if (typeof value === 'number' && key.includes('budget')) return value.toLocaleString('en-PH', { minimumFractionDigits: 2 });
        return String(value);
    };

    return (
        <div className="print-area rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div className="mb-4 text-center">
                <p className="text-xs uppercase tracking-widest text-gray-500">Eastern Visayas State University · ORIES</p>
                <h2 className="text-xl font-bold text-gray-900">{report.title}</h2>
                <p className="text-sm text-gray-600">
                    {report.meta.type === 'opcr' ? '' : `${formatDate(report.meta.from)} to ${formatDate(report.meta.to)} · `}{report.meta.scope}
                </p>
            </div>
            {report.rows.length === 0 ? (
                <p className="py-8 text-center text-sm text-gray-500">No verified records match these filters.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full border-collapse text-sm">
                        <thead>
                            <tr className="border-y-2 border-gray-800 text-left">
                                {report.columns.map((c) => <th key={c.key} className="px-3 py-2 font-semibold">{c.label}</th>)}
                            </tr>
                        </thead>
                        <tbody>
                            {report.rows.map((row, i) => (
                                <tr key={i} className="border-b border-gray-100">
                                    {report.columns.map((c) => (
                                        <td key={c.key} className={`px-3 py-2 ${typeof row[c.key] === 'number' ? 'text-right tabular-nums' : ''}`}>{display(row[c.key], c.key)}</td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                        {report.totals && (
                            <tfoot>
                                <tr className="border-y-2 border-gray-800 font-semibold">
                                    {report.columns.map((c) => (
                                        <td key={c.key} className={`px-3 py-2 ${typeof report.totals?.[c.key] === 'number' ? 'text-right tabular-nums' : ''}`}>
                                            {report.totals?.[c.key] !== undefined ? display(report.totals[c.key], c.key) : ''}
                                        </td>
                                    ))}
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            )}
            <p className="mt-4 text-right text-xs text-gray-500">Generated by {report.meta.generated_by} on {formatDate(report.meta.generated_at, true)} · ORIES-IS</p>
        </div>
    );
}
