import { formatDate, INDEXING_LABELS, OUTCOME_LABELS } from '@/lib/format';
import type { ProjectUpdate } from '@/lib/types';

/**
 * The submitted values of a stage or outcome update.
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

    return update.remarks ? <p className="text-sm text-gray-600">Note: {update.remarks}</p> : null;
}
