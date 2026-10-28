import { formatDate, formatPeso, INDEXING_LABELS, OUTCOME_LABELS } from '@/lib/format';
import type { ProjectUpdate } from '@/lib/types';

/**
 * The submitted values of a stage, outcome, or change-of-details update.
 */
export function UpdateDetails({ update }: { update: ProjectUpdate }) {
    const d = update.data;

    if (update.type === 'stage') {
        return (
            <p className="text-sm text-gray-700">
                Effective date: <strong>{formatDate(d?.date)}</strong>
                {update.remarks && <span className="block text-gray-600">Note: {update.remarks}</span>}
            </p>
        );
    }

    if (update.type === 'outcome' && d) {
        return (
            <dl className="grid grid-cols-3 gap-y-1 rounded-md bg-gray-50 p-3 text-sm">
                <dt className="text-gray-500">Outcome</dt><dd className="col-span-2">{OUTCOME_LABELS[d.type ?? ''] ?? d.type}</dd>
                <dt className="text-gray-500">Title</dt><dd className="col-span-2">{d.title}</dd>
                {d.venue && <><dt className="text-gray-500">Journal / venue / adopter</dt><dd className="col-span-2">{d.venue}</dd></>}
                {d.indexing && <><dt className="text-gray-500">Indexing</dt><dd className="col-span-2">{INDEXING_LABELS[d.indexing]}</dd></>}
                {d.date && <><dt className="text-gray-500">Date</dt><dd className="col-span-2">{formatDate(d.date)}</dd></>}
                {d.citations != null && <><dt className="text-gray-500">Citations</dt><dd className="col-span-2">{d.citations}</dd></>}
                {d.url && <><dt className="text-gray-500">Link</dt><dd className="col-span-2 truncate"><a href={d.url} target="_blank" rel="noopener noreferrer" className="text-brand-700 underline">{d.url}</a></dd></>}
                {update.remarks && <><dt className="text-gray-500">Note</dt><dd className="col-span-2">{update.remarks}</dd></>}
            </dl>
        );
    }

    if (update.type === 'details' && d) {
        const names = (list: { name: string }[]) => list.map((m) => m.name).join(', ') || 'None';
        const labels = (list: { label: string }[]) => list.map((t) => t.label).join(', ') || 'None';

        return (
            <div className="space-y-2 text-sm">
                <div className="overflow-x-auto rounded-md border border-gray-200">
                    <table className="min-w-full text-left">
                        <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-3 py-2">Field</th>
                                <th className="px-3 py-2">{update.status === 'verified' ? 'Before' : 'Current'}</th>
                                <th className="px-3 py-2">{update.status === 'verified' ? 'After' : 'Proposed'}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {Object.entries(d.fields ?? {}).map(([field, { from, to }]) => (
                                <tr key={field}>
                                    <td className="px-3 py-2 text-gray-500">{DETAIL_LABELS[field] ?? field}</td>
                                    <td className="px-3 py-2 text-gray-600 line-through decoration-gray-400">{detailValue(field, from)}</td>
                                    <td className="px-3 py-2 font-medium text-gray-900">{detailValue(field, to)}</td>
                                </tr>
                            ))}
                            {d.members && (
                                <tr>
                                    <td className="px-3 py-2 text-gray-500">Members</td>
                                    <td className="px-3 py-2 text-gray-600">{names(d.members.from)}</td>
                                    <td className="px-3 py-2 font-medium text-gray-900">{names(d.members.to)}</td>
                                </tr>
                            )}
                            {d.tags && (
                                <tr>
                                    <td className="px-3 py-2 text-gray-500">Contributor tags</td>
                                    <td className="px-3 py-2 text-gray-600">{labels(d.tags.from)}</td>
                                    <td className="px-3 py-2 font-medium text-gray-900">{labels(d.tags.to)}</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                {update.remarks && <p className="text-gray-700"><span className="text-gray-500">Reason:</span> {update.remarks}</p>}
            </div>
        );
    }

    return update.remarks ? <p className="text-sm text-gray-600">Note: {update.remarks}</p> : null;
}

const DETAIL_LABELS: Record<string, string> = {
    title: 'Title',
    abstract: 'Abstract',
    keywords: 'Keywords',
    funding_source: 'Funding source',
    budget: 'Budget',
    start_date: 'Start date',
    end_date: 'End date',
};

function detailValue(field: string, value: string | number | null): string {
    if (value === null || value === '') return '—';
    if (field === 'start_date' || field === 'end_date') return formatDate(String(value));
    if (field === 'budget') return formatPeso(value);
    return String(value);
}
