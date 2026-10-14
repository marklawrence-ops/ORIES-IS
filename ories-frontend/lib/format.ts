import type { Office, Role, Stage, VerificationStatus } from './types';

export const ROLE_LABELS: Record<Role, string> = {
    faculty: 'Faculty / Non-teaching',
    head: 'Head / Dean',
    staff: 'ORIES Office / Technical Staff',
    director: 'Director',
    vp: 'Vice President, ORIES',
    oup: 'OUP / Planning Office (view-only)',
};

export const OFFICE_LABELS: Record<Office, string> = {
    research: 'Research',
    innovation: 'Innovation',
    extension: 'Extension',
};

export const STAGE_LABELS: Record<Stage, string> = {
    proposal: 'Proposal',
    ongoing: 'Ongoing',
    completed: 'Completed',
    terminated: 'Terminated',
};

export const VERIFICATION_LABELS: Record<VerificationStatus, string> = {
    pending: 'Awaiting verification',
    verified: 'Verified',
    returned: 'Returned for correction',
};

export const OUTCOME_LABELS: Record<string, string> = {
    publication: 'Publication',
    presentation: 'Paper presentation',
    commercialization: 'Commercialization',
    policy: 'Adopted as policy',
    technology_transfer: 'Technology transfer',
};

export const INDEXING_LABELS: Record<string, string> = {
    scopus: 'Scopus',
    wos: 'Web of Science',
    aci: 'ASEAN Citation Index',
    other: 'Other indexed',
    none: 'Not indexed',
};

export const CERTIFICATE_LABELS: Record<string, string> = {
    participation: 'Certificate of Participation',
    completion: 'Certificate of Completion',
    publication: 'Certificate of Publication',
    utilization: 'Certificate of Utilization',
};

export const PURPOSE_LABELS: Record<string, string> = {
    promotion: 'Promotion',
    reclassification: 'Reclassification',
    ipcr: 'IPCR documentation',
    other: 'Other',
};

export const METRIC_LABELS: Record<string, string> = {
    proposals: 'Proposals',
    ongoing: 'Ongoing',
    completed: 'Completed',
    publications: 'Publications',
    scopus: 'Scopus-indexed publications',
    commercialization: 'Commercialized',
    policy: 'Adopted as policy',
    technology_transfer: 'Technology transfer',
};

export function formatDate(value: string | null | undefined, withTime = false): string {
    if (!value) return '—';
    const date = new Date(value.length === 10 ? `${value}T00:00:00` : value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        ...(withTime ? { hour: 'numeric', minute: '2-digit' } : {}),
    });
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function formatPeso(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') return '—';
    return Number(value).toLocaleString('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });
}

export function canSubmit(role: Role | undefined): boolean {
    return role === 'faculty' || role === 'head';
}
