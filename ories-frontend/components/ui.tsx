import Link from 'next/link';
import type { ButtonHTMLAttributes, InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';
import { formatDate, OFFICE_LABELS, STAGE_LABELS, VERIFICATION_LABELS } from '@/lib/format';
import type { Office, Stage, UpdateStatus } from '@/lib/types';

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost';

const VARIANTS: Record<Variant, string> = {
    primary: 'bg-brand-700 text-white hover:bg-brand-800 disabled:bg-brand-200',
    secondary: 'border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 disabled:text-gray-400',
    danger: 'bg-red-600 text-white hover:bg-red-700 disabled:bg-red-300',
    ghost: 'text-brand-700 hover:bg-brand-50',
};

export function Button({ variant = 'primary', className = '', ...props }: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: Variant }) {
    return (
        <button
            className={`inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors disabled:cursor-not-allowed ${VARIANTS[variant]} ${className}`}
            {...props}
        />
    );
}

export function ButtonLink({ href, variant = 'primary', className = '', children }: { href: string; variant?: Variant; className?: string; children: ReactNode }) {
    return (
        <Link href={href} className={`inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors ${VARIANTS[variant]} ${className}`}>
            {children}
        </Link>
    );
}

export function Card({ title, actions, children, className = '' }: { title?: ReactNode; actions?: ReactNode; children: ReactNode; className?: string }) {
    return (
        <section className={`rounded-lg border border-gray-200 bg-white shadow-sm ${className}`}>
            {(title || actions) && (
                <header className="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-3">
                    <h2 className="text-sm font-semibold text-gray-900">{title}</h2>
                    {actions}
                </header>
            )}
            <div className="p-5">{children}</div>
        </section>
    );
}

export function PageHeader({ title, description, actions }: { title: string; description?: ReactNode; actions?: ReactNode }) {
    return (
        <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 className="text-2xl font-bold text-gray-900">{title}</h1>
                {description && <p className="mt-1 text-sm text-gray-600">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap gap-2 no-print">{actions}</div>}
        </div>
    );
}

const BADGE_TONES = {
    gray: 'bg-gray-100 text-gray-700',
    blue: 'bg-blue-50 text-blue-700',
    green: 'bg-green-50 text-green-700',
    amber: 'bg-amber-50 text-amber-800',
    red: 'bg-red-50 text-red-700',
    purple: 'bg-purple-50 text-purple-700',
    brand: 'bg-brand-50 text-brand-700',
};

export function Badge({ tone = 'gray', children }: { tone?: keyof typeof BADGE_TONES; children: ReactNode }) {
    return <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${BADGE_TONES[tone]}`}>{children}</span>;
}

export function StageBadge({ stage }: { stage: Stage }) {
    const tone = { proposal: 'gray', ongoing: 'blue', completed: 'green', terminated: 'red' } as const;
    return <Badge tone={tone[stage]}>{STAGE_LABELS[stage]}</Badge>;
}

export function VerificationBadge({ status }: { status: UpdateStatus }) {
    const tone = { pending: 'amber', verified: 'green', returned: 'red', withdrawn: 'gray' } as const;
    return <Badge tone={tone[status]}>{VERIFICATION_LABELS[status]}</Badge>;
}

export function DeadlineBadge({ deadline, endDate }: { deadline?: 'overdue' | 'ending_soon' | null; endDate?: string | null }) {
    if (!deadline) return null;
    return deadline === 'overdue'
        ? <Badge tone="red">Past end date{endDate ? ` (${formatDate(endDate)})` : ''}</Badge>
        : <Badge tone="amber">Ends {endDate ? formatDate(endDate) : 'soon'}</Badge>;
}

export function OfficeBadge({ office }: { office: Office }) {
    const tone = { research: 'brand', innovation: 'purple', extension: 'blue' } as const;
    return <Badge tone={tone[office]}>{OFFICE_LABELS[office]}</Badge>;
}

export function Field({ label, hint, error, children }: { label: string; hint?: ReactNode; error?: string | null; children: ReactNode }) {
    return (
        <label className="block">
            <span className="block text-sm font-medium text-gray-700">{label}</span>
            <div className="mt-1">{children}</div>
            {hint && !error && <span className="mt-1 block text-xs text-gray-500">{hint}</span>}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}

const INPUT = 'block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 disabled:bg-gray-50';

export function Input(props: InputHTMLAttributes<HTMLInputElement>) {
    return <input {...props} className={`${INPUT} ${props.className ?? ''}`} />;
}

export function Select(props: SelectHTMLAttributes<HTMLSelectElement>) {
    return <select {...props} className={`${INPUT} ${props.className ?? ''}`} />;
}

export function Textarea(props: TextareaHTMLAttributes<HTMLTextAreaElement>) {
    return <textarea rows={3} {...props} className={`${INPUT} ${props.className ?? ''}`} />;
}

export function Alert({ tone = 'red', children }: { tone?: 'red' | 'amber' | 'green' | 'blue'; children: ReactNode }) {
    const tones = {
        red: 'border-red-200 bg-red-50 text-red-800',
        amber: 'border-amber-200 bg-amber-50 text-amber-900',
        green: 'border-green-200 bg-green-50 text-green-800',
        blue: 'border-blue-200 bg-blue-50 text-blue-800',
    };
    return <div className={`rounded-md border px-4 py-3 text-sm ${tones[tone]}`}>{children}</div>;
}

export function EmptyState({ children }: { children: ReactNode }) {
    return <div className="rounded-lg border-2 border-dashed border-gray-200 p-8 text-center text-sm text-gray-500">{children}</div>;
}

export function Loading({ label = 'Loading…' }: { label?: string }) {
    return <div className="py-10 text-center text-sm text-gray-500">{label}</div>;
}

export function Modal({ open, title, onClose, children }: { open: boolean; title: string; onClose: () => void; children: ReactNode }) {
    if (!open) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true" onClick={onClose}>
            <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between border-b px-5 py-3">
                    <h3 className="font-semibold text-gray-900">{title}</h3>
                    <button onClick={onClose} className="text-xl leading-none text-gray-400 hover:text-gray-700" aria-label="Close">&times;</button>
                </div>
                <div className="p-5">{children}</div>
            </div>
        </div>
    );
}

export function Pagination({ page, lastPage, onChange }: { page: number; lastPage: number; onChange: (page: number) => void }) {
    if (lastPage <= 1) return null;
    return (
        <div className="mt-4 flex items-center justify-end gap-2 text-sm">
            <Button variant="secondary" disabled={page <= 1} onClick={() => onChange(page - 1)}>Previous</Button>
            <span className="text-gray-600">Page {page} of {lastPage}</span>
            <Button variant="secondary" disabled={page >= lastPage} onClick={() => onChange(page + 1)}>Next</Button>
        </div>
    );
}
