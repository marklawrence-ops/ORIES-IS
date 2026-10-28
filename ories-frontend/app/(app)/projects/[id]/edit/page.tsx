'use client';

import { useParams } from 'next/navigation';
import ProposalForm from '@/components/ProposalForm';
import { Alert, Loading, PageHeader } from '@/components/ui';
import { useApi } from '@/lib/hooks';
import type { ProjectDetail } from '@/lib/types';

export default function ResubmitProposalPage() {
    const { id } = useParams<{ id: string }>();
    const { data, error, loading } = useApi<ProjectDetail>(`/projects/${id}`);

    if (loading) return <Loading />;
    if (error || !data) return <Alert>{error ?? 'Record not found.'}</Alert>;
    if (!data.permissions.can_resubmit) return <Alert>Only the lead can edit a proposal, and only after it is returned for correction.</Alert>;

    // The latest proposal submission; a withdrawn one has no reviewer remarks to show
    const lastProposal = data.updates.find((u) => u.type === 'proposal');
    const lastReturn = lastProposal?.status === 'returned' ? lastProposal : null;

    return (
        <div className="mx-auto max-w-4xl">
            <PageHeader title="Correct and Resubmit Proposal" description={data.project.title} />
            <ProposalForm existing={data.project} returnRemarks={lastReturn?.review_remarks} wasWithdrawn={lastProposal?.status === 'withdrawn'} />
        </div>
    );
}
