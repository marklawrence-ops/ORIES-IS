'use client';

import { useParams } from 'next/navigation';
import ProposalForm from '@/components/ProposalForm';
import { Alert, Loading, PageHeader } from '@/components/ui';
import { useApi } from '@/lib/hooks';
import type { ProjectDetail } from '@/lib/types';

export default function ChangeDetailsPage() {
    const { id } = useParams<{ id: string }>();
    const { data, error, loading } = useApi<ProjectDetail>(`/projects/${id}`);

    if (loading) return <Loading />;
    if (error || !data) return <Alert>{error ?? 'Record not found.'}</Alert>;
    if (!data.permissions.can_change_details) {
        return <Alert>Only the lead can change a verified record&apos;s details, and only while no other update is awaiting verification.</Alert>;
    }

    return (
        <div className="mx-auto max-w-4xl">
            <PageHeader title="Change Record Details" description={data.project.title} />
            <ProposalForm existing={data.project} mode="change" />
        </div>
    );
}
